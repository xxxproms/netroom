<?php

namespace App\Support\Import;

use App\Actions\CreatePortsFromModel;
use App\Models\Device;
use App\Models\DeviceModel;
use App\Models\Port;
use App\Models\Site;
use App\Models\Vlan;
use App\Models\VlanDomain;
use Illuminate\Support\Facades\DB;

/**
 * Writes a parsed workbook into the panel. The two adjacent complexes share one
 * VLAN plan, so they land in one domain with a site each; every switch becomes
 * a device with its ports and their VLAN membership. Re-running skips switches
 * already imported, so it is safe to preview, fix the sheet, and run again.
 */
class ApplySwitchImport
{
    public function __construct(private readonly CreatePortsFromModel $ports = new CreatePortsFromModel) {}

    /**
     * @param  array<string, mixed>  $parsed  the output of SwitchWorkbookParser
     * @return array<string, int>
     */
    public function apply(array $parsed): array
    {
        return DB::transaction(function () use ($parsed) {
            $domain = VlanDomain::firstOrCreate(['name' => $parsed['domain']]);

            $sites = [];
            foreach ($parsed['sites'] as $site) {
                $sites[$site['code']] = Site::firstOrCreate(
                    ['code' => $site['code']],
                    ['vlan_domain_id' => $domain->id, 'name' => $site['name'], 'kind' => 'complex'],
                );
            }

            $vlans = $this->vlans($domain, $parsed['vlans']);

            $created = 0;
            $skipped = 0;
            $memberships = 0;

            foreach ($parsed['switches'] as $switch) {
                if ($switch['site'] === null) {
                    $skipped++;

                    continue;
                }

                $site = $sites[$switch['site']];

                if (Device::where('site_id', $site->id)->where('name', $switch['name'])->exists()) {
                    $skipped++;

                    continue;
                }

                $device = $this->device($site, $switch);
                $created++;
                $memberships += $this->applyMemberships($device, $switch, $vlans);
            }

            return [
                'sites' => count($sites),
                'vlans' => count($vlans),
                'devices' => $created,
                'skipped' => $skipped,
                'memberships' => $memberships,
            ];
        });
    }

    /**
     * @param  list<array{vid: int, name: string}>  $plan
     * @return array<int, Vlan> keyed by VID
     */
    private function vlans(VlanDomain $domain, array $plan): array
    {
        $vlans = [];

        foreach ($plan as $entry) {
            $vlans[$entry['vid']] = Vlan::firstOrCreate(
                ['vlan_domain_id' => $domain->id, 'vid' => $entry['vid']],
                ['name' => $entry['name']],
            );
        }

        return $vlans;
    }

    /**
     * @param  array<string, mixed>  $switch
     */
    private function device(Site $site, array $switch): Device
    {
        $model = $this->model($switch);

        $device = Device::create([
            'device_model_id' => $model->id,
            'site_id' => $site->id,
            'name' => $switch['name'],
            'status' => 'active',
            'mgmt_ip' => $this->uniqueMgmtIp($switch['mgmt_ip']),
            'mgmt_url' => $switch['mgmt_ip'] ? "http://{$switch['mgmt_ip']}/" : null,
        ]);

        $this->ports->handle($device);

        if ($switch['uplinks'] !== []) {
            $device->ports()
                ->where('role', 'network')
                ->whereIn('number', $switch['uplinks'])
                ->update(['is_uplink' => true]);
        }

        return $device;
    }

    /**
     * Finds the catalogue model named in the sheet, or builds one to fit when
     * the model is unknown or the sheet has more ports than the model has — so
     * no membership is dropped for want of a port to hang it on.
     *
     * @param  array<string, mixed>  $switch
     */
    private function model(array $switch): DeviceModel
    {
        $existing = DeviceModel::where('model', $switch['model'])->first();

        if ($existing instanceof DeviceModel && $existing->portTemplates()->sum('count') >= $switch['port_count']) {
            return $existing;
        }

        $ports = max($switch['port_count'], 1);
        $model = DeviceModel::firstOrCreate(
            ['vendor' => 'D-Link', 'model' => ($switch['model'] ?? 'Unknown')." ({$ports}p, imported)"],
            ['kind' => 'switch', 'u_height' => 1],
        );

        if (! $model->portTemplates()->exists()) {
            $model->portTemplates()->create([
                'start_number' => 1, 'count' => $ports, 'media' => 'rj45', 'role' => 'network', 'sort' => 0,
            ]);
        }

        return $model;
    }

    /**
     * @param  array<string, mixed>  $switch
     * @param  array<int, Vlan>  $vlans
     * @return int the memberships actually written
     */
    private function applyMemberships(Device $device, array $switch, array $vlans): int
    {
        $ports = $device->ports()
            ->where('role', 'network')
            ->get()
            ->keyBy('number');

        $written = 0;

        foreach ($switch['memberships'] as $membership) {
            $vlan = $vlans[$membership['vid']] ?? null;
            $port = $ports[$membership['port']] ?? null;

            if (! $vlan instanceof Vlan || ! $port instanceof Port) {
                continue;
            }

            $port->vlans()->syncWithoutDetaching([
                $vlan->id => ['mode' => $membership['mode']],
            ]);
            $written++;
        }

        return $written;
    }

    /**
     * Management IPs are unique across the estate; a duplicate in the sheet is
     * left blank rather than failing the whole import.
     */
    private function uniqueMgmtIp(?string $ip): ?string
    {
        if ($ip === null || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        return Device::where('mgmt_ip', $ip)->exists() ? null : $ip;
    }
}
