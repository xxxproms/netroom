<?php

namespace App\Support\Export;

use App\Models\Device;
use App\Models\Subnet;
use App\Models\Vlan;
use App\Models\Workplace;
use App\Support\SiteContext;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Writes the documented estate back out to a workbook: a sheet each for
 * devices, VLANs, subnets and workplaces, scoped to what the user can reach.
 * The counterpart to the importer — a backup the department can keep or hand on.
 */
class InventoryExport
{
    public function __construct(private readonly SiteContext $context) {}

    /**
     * Build the workbook and return the raw xlsx bytes.
     */
    public function contents(): string
    {
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);

        $this->sheet($book, 'Devices', ['Name', 'Model', 'Site', 'Mgmt IP', 'Status'], $this->devices());
        $this->sheet($book, 'VLANs', ['VID', 'Name', 'Domain'], $this->vlans());
        $this->sheet($book, 'Subnets', ['CIDR', 'Name', 'Gateway', 'Domain'], $this->subnets());
        $this->sheet($book, 'Workplaces', ['Name', 'Person', 'Site'], $this->workplaces());

        $book->setActiveSheetIndex(0);

        ob_start();
        (new Xlsx($book))->save('php://output');

        return (string) ob_get_clean();
    }

    /**
     * @param  list<string>  $headings
     * @param  array<int, array<int, string|int|null>>  $rows
     */
    private function sheet(Spreadsheet $book, string $title, array $headings, array $rows): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle($title);
        $sheet->fromArray($headings, null, 'A1');
        $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getFont()->setBold(true);

        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A2');
        }
    }

    /**
     * @return array<int, array<int, string|int|null>>
     */
    private function devices(): array
    {
        return $this->context->scope(Device::query())
            ->with(['site:id,name', 'deviceModel:id,vendor,model'])
            ->orderBy('name')
            ->get()
            ->map(fn (Device $device) => [
                $device->name,
                $device->deviceModel?->model,
                $device->site?->name,
                $device->mgmt_ip,
                $device->status,
            ])
            ->all();
    }

    /**
     * @return array<int, array<int, string|int|null>>
     */
    private function vlans(): array
    {
        return Vlan::whereIn('vlan_domain_id', $this->domainIds())
            ->with('vlanDomain:id,name')
            ->orderBy('vid')
            ->get()
            ->map(fn (Vlan $vlan) => [
                $vlan->vid,
                $vlan->name,
                $vlan->vlanDomain?->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array<int, string|int|null>>
     */
    private function subnets(): array
    {
        return Subnet::whereIn('vlan_domain_id', $this->domainIds())
            ->with('vlanDomain:id,name')
            ->orderBy('network')
            ->get()
            ->map(fn (Subnet $subnet) => [
                $subnet->cidr,
                $subnet->name,
                $subnet->gateway,
                $subnet->vlanDomain?->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array<int, string|int|null>>
     */
    private function workplaces(): array
    {
        return $this->context->scope(Workplace::query())
            ->with('site:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Workplace $workplace) => [
                $workplace->name,
                $workplace->person,
                $workplace->site?->name,
            ])
            ->all();
    }

    /**
     * @return Collection<int, int>
     */
    private function domainIds(): Collection
    {
        return $this->context->available()->pluck('vlan_domain_id')->unique()->values();
    }
}
