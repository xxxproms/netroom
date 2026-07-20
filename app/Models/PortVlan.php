<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * How one VLAN sits on one port: tagged on a trunk, untagged on an access port.
 *
 * @property int $port_id
 * @property int $vlan_id
 * @property string $mode
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PortVlan extends Pivot
{
    public const MODES = ['tagged', 'untagged'];

    protected $table = 'port_vlan';

    public $incrementing = true;
}
