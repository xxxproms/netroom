<?php

namespace App\Support\Export;

use App\Models\Cable;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Port;
use App\Models\Subnet;
use App\Models\Vlan;
use App\Models\Workplace;
use App\Support\SiteContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
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

        $this->sheet($book, 'Devices', ['Name', 'Model', 'Site', 'Mgmt IP', 'Status'], $this->devices(), 5);
        $this->sheet($book, 'Commutation', [
            'Label', 'Device A', 'Port A', 'Device / workplace B', 'Port / socket B',
            'Media', 'Strands', 'Length cm', 'Colour', 'Status',
        ], $this->commutation(), 10);
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
     * @param  int|null  $statusColumn  1-based column to tint by status, if any.
     */
    private function sheet(Spreadsheet $book, string $title, array $headings, array $rows, ?int $statusColumn = null): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle($title);
        $sheet->fromArray($headings, null, 'A1');

        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A2');
        }

        SheetStyler::apply($sheet, count($headings), count($rows), $statusColumn);
    }

    /**
     * Every cable at the site, both ends named the way the journal shows them.
     *
     * @return array<int, array<int, string|int|null>>
     */
    private function commutation(): array
    {
        $ends = function (Relation $morph): void {
            if ($morph instanceof MorphTo) {
                $morph->morphWith([
                    Port::class => ['device'],
                    Outlet::class => ['workplace'],
                ]);
            }
        };

        return $this->context->scope(Cable::query())
            ->with(['a' => $ends, 'b' => $ends])
            ->orderBy('id')
            ->get()
            ->map(fn (Cable $cable) => [
                $cable->label,
                $this->endOwner($cable->a),
                $this->endSocket($cable->a),
                $this->endOwner($cable->b),
                $this->endSocket($cable->b),
                $cable->media,
                $cable->strands,
                $cable->length_cm,
                $cable->color,
                $cable->status,
            ])
            ->all();
    }

    /** The device or workplace an end belongs to. */
    private function endOwner(Model $end): ?string
    {
        return match (true) {
            $end instanceof Port => $end->device?->name,
            $end instanceof Outlet => $end->workplace?->name,
            default => null,
        };
    }

    /** The port name or socket label at an end. */
    private function endSocket(Model $end): ?string
    {
        return match (true) {
            $end instanceof Port => $end->name,
            $end instanceof Outlet => $end->label,
            default => null,
        };
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
