<?php

namespace App\Actions;

use App\Models\Device;
use App\Models\DeviceModel;
use Illuminate\Support\Facades\DB;

/**
 * Adds a server to a rack without a trip to the model catalogue: the caller
 * gives a height and a port count, and this stands up an ad-hoc "Generic"
 * server model (reused when an identical one already exists), then the device
 * and its ports. The heavy lifting of laying out ports is left to
 * CreatePortsFromModel, so a quick server ends up indistinguishable from one
 * built the long way.
 */
class CreateQuickServer
{
    public function __construct(private readonly CreatePortsFromModel $ports) {}

    /**
     * @param  array<string, mixed>  $data  Validated server attributes, including
     *                                      u_height, port_count and port_media.
     */
    public function handle(array $data): Device
    {
        return DB::transaction(function () use ($data) {
            $model = $this->model(
                (int) $data['u_height'],
                (int) $data['port_count'],
                (string) $data['port_media'],
            );

            $device = Device::create([
                'device_model_id' => $model->id,
                'site_id' => $data['site_id'],
                'rack_id' => $data['rack_id'],
                'position_u' => $data['position_u'],
                'face' => $data['face'],
                'name' => $data['name'],
                'mgmt_ip' => $data['mgmt_ip'] ?? null,
                'status' => $data['status'],
                'color' => $data['color'] ?? null,
            ]);

            $this->ports->handle($device);

            return $device;
        });
    }

    /**
     * The catalogue entry backing a server of this shape — one per
     * height/port/media combination, so the catalogue does not fill up with a
     * near-identical model per server.
     */
    private function model(int $height, int $count, string $media): DeviceModel
    {
        $name = sprintf('Server %dU · %d x %s', $height, $count, $this->mediaLabel($media));

        $model = DeviceModel::firstOrCreate(
            ['vendor' => 'Generic', 'model' => $name],
            ['kind' => 'server', 'u_height' => $height],
        );

        if (! $model->portTemplates()->exists()) {
            $model->portTemplates()->create([
                'name_prefix' => '',
                'start_number' => 1,
                'count' => $count,
                'media' => $media,
                'speed_mbps' => $this->speed($media),
                'role' => 'network',
                'sort' => 0,
            ]);
        }

        return $model;
    }

    private function mediaLabel(string $media): string
    {
        return match ($media) {
            'rj45' => 'RJ45',
            'sfp' => 'SFP',
            'sfp_plus' => 'SFP+',
            'lc' => 'LC',
            'sc' => 'SC',
            default => strtoupper($media),
        };
    }

    private function speed(string $media): ?int
    {
        return match ($media) {
            'rj45', 'sfp' => 1000,
            'sfp_plus', 'lc', 'sc' => 10000,
            default => null,
        };
    }
}
