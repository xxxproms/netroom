<?php

namespace Database\Seeders;

use App\Models\DeviceModel;
use Illuminate\Database\Seeder;

class DeviceModelSeeder extends Seeder
{
    /**
     * A starting catalogue: the switches this panel was first built for, the
     * two firewall/router platforms that terminate site-to-site tunnels, and
     * generic patch panels. Every entry is editable afterwards.
     *
     * @var list<array{vendor: string, model: string, kind: string, u_height?: int, ports: list<array{count: int, media?: string, speed_mbps?: int|null, role?: string, name_prefix?: string, start_number?: int}>}>
     */
    private array $catalog = [
        [
            'vendor' => 'D-Link',
            'model' => 'DGS-1210-28/ME',
            'kind' => 'switch',
            'ports' => [
                ['count' => 24, 'media' => 'rj45', 'speed_mbps' => 1000],
                ['count' => 4, 'media' => 'sfp', 'speed_mbps' => 1000, 'start_number' => 25],
            ],
        ],
        [
            'vendor' => 'D-Link',
            'model' => 'DGS-1210-12TS/ME',
            'kind' => 'switch',
            'ports' => [
                ['count' => 2, 'media' => 'rj45', 'speed_mbps' => 1000],
                ['count' => 10, 'media' => 'sfp', 'speed_mbps' => 1000, 'start_number' => 3],
            ],
        ],
        [
            'vendor' => 'D-Link',
            'model' => 'DES-1210-28/ME',
            'kind' => 'switch',
            'ports' => [
                ['count' => 24, 'media' => 'rj45', 'speed_mbps' => 100],
                ['count' => 4, 'media' => 'sfp', 'speed_mbps' => 1000, 'start_number' => 25],
            ],
        ],
        [
            'vendor' => 'D-Link',
            'model' => 'DES-1210-10/ME',
            'kind' => 'switch',
            'ports' => [
                ['count' => 8, 'media' => 'rj45', 'speed_mbps' => 100],
                ['count' => 2, 'media' => 'sfp', 'speed_mbps' => 1000, 'start_number' => 9],
            ],
        ],
        [
            'vendor' => 'D-Link',
            'model' => 'DGS-1100-24',
            'kind' => 'switch',
            'ports' => [
                ['count' => 24, 'media' => 'rj45', 'speed_mbps' => 1000],
            ],
        ],
        [
            'vendor' => 'D-Link',
            'model' => 'DES-1100-24',
            'kind' => 'switch',
            'ports' => [
                ['count' => 24, 'media' => 'rj45', 'speed_mbps' => 100],
            ],
        ],
        [
            'vendor' => 'Generic',
            'model' => 'Patch panel 24 x RJ45',
            'kind' => 'patch_panel',
            'ports' => [
                ['count' => 24, 'media' => 'rj45', 'role' => 'front', 'speed_mbps' => null],
                ['count' => 24, 'media' => 'rj45', 'role' => 'rear', 'speed_mbps' => null],
            ],
        ],
        [
            'vendor' => 'Generic',
            'model' => 'Patch panel 48 x RJ45',
            'kind' => 'patch_panel',
            'u_height' => 2,
            'ports' => [
                ['count' => 48, 'media' => 'rj45', 'role' => 'front', 'speed_mbps' => null],
                ['count' => 48, 'media' => 'rj45', 'role' => 'rear', 'speed_mbps' => null],
            ],
        ],
        [
            'vendor' => 'Generic',
            'model' => 'Fibre patch panel 12 x LC',
            'kind' => 'patch_panel',
            'ports' => [
                ['count' => 12, 'media' => 'lc', 'role' => 'front', 'speed_mbps' => null],
                ['count' => 12, 'media' => 'lc', 'role' => 'rear', 'speed_mbps' => null],
            ],
        ],
        [
            'vendor' => 'Kerio',
            'model' => 'Kerio Control',
            'kind' => 'firewall',
            'ports' => [
                ['count' => 4, 'media' => 'rj45', 'speed_mbps' => 1000],
            ],
        ],
        [
            'vendor' => 'MikroTik',
            'model' => 'RouterBOARD (generic)',
            'kind' => 'router',
            'ports' => [
                ['count' => 5, 'media' => 'rj45', 'speed_mbps' => 1000],
            ],
        ],
    ];

    public function run(): void
    {
        foreach ($this->catalog as $entry) {
            $model = DeviceModel::firstOrCreate(
                ['vendor' => $entry['vendor'], 'model' => $entry['model']],
                ['kind' => $entry['kind'], 'u_height' => $entry['u_height'] ?? 1],
            );

            if ($model->portTemplates()->exists()) {
                continue;
            }

            foreach ($entry['ports'] as $sort => $ports) {
                $model->portTemplates()->create([
                    'name_prefix' => $ports['name_prefix'] ?? '',
                    'start_number' => $ports['start_number'] ?? 1,
                    'count' => $ports['count'],
                    'media' => $ports['media'] ?? 'rj45',
                    'speed_mbps' => $ports['speed_mbps'] ?? null,
                    'role' => $ports['role'] ?? 'network',
                    'sort' => $sort,
                ]);
            }
        }
    }
}
