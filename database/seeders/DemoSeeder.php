<?php

namespace Database\Seeders;

use App\Actions\CreatePortsFromModel;
use App\Models\Device;
use App\Models\DeviceModel;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\User;
use App\Models\Vlan;
use App\Models\VlanDomain;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * A fictional estate to look at NetRoom with something in it: two neighbouring
 * complexes sharing one VLAN plan, a town office, a factory and a cottage.
 *
 * Never seeded automatically — run it on a scratch database only:
 *
 *     php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    /** @var array<string, Site> */
    private array $sites = [];

    /** @var array<string, Rack> */
    private array $racks = [];

    /**
     * Users who only see part of the estate, by site code.
     *
     * @var array<string, list<string>>
     */
    private array $restricted = [];

    public function run(): void
    {
        $this->call([PermissionSeeder::class, DeviceModelSeeder::class]);

        $engineer = $this->users();

        // Seeding while signed in so the activity log has someone to name.
        Auth::login($engineer);

        $this->sites();
        $this->grantSites();
        $this->vlans();
        $this->rooms();
        $this->devices();

        Auth::logout();
    }

    /**
     * One account per role, so every access level can be tried out. The
     * technician and the viewer see part of the estate, not all of it.
     */
    private function users(): User
    {
        $accounts = [
            ['Администратор панели', 'admin@example.com', 'admin', true, []],
            ['Сетевой инженер', 'engineer@example.com', 'engineer', true, []],
            ['Техник кроссовых', 'tech@example.com', 'technician', false, ['NORTH', 'SOUTH']],
            ['Наблюдатель', 'viewer@example.com', 'viewer', false, ['CITY']],
        ];

        foreach ($accounts as [$name, $email, $role, $everywhere, $codes]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'locale' => 'ru',
                    'has_all_sites' => $everywhere,
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([$role]);

            if ($codes !== []) {
                $this->restricted[$email] = $codes;
            }

            $this->command->info("{$email} / password — {$role}");
        }

        /** @var User $engineer */
        $engineer = User::firstWhere('email', 'engineer@example.com');

        return $engineer;
    }

    private function sites(): void
    {
        // The two complexes stand side by side on one switched network, so they
        // share a VLAN plan; everyone else numbers VLANs their own way.
        $campus = VlanDomain::firstOrCreate(['name' => 'Кампус (Северный + Южный)']);
        $city = VlanDomain::firstOrCreate(['name' => 'Городской офис']);
        $plant = VlanDomain::firstOrCreate(['name' => 'Фабрика']);
        $cottage = VlanDomain::firstOrCreate(['name' => 'Коттеджи']);

        $sites = [
            ['NORTH', 'Северный комплекс', 'complex', $campus, 'Алматы', 'пр. Достык, 12', '#0284c7', 240, 200],
            ['SOUTH', 'Южный комплекс', 'complex', $campus, 'Алматы', 'пр. Достык, 18', '#0891b2', 420, 260],
            ['CITY', 'Городской офис', 'office', $city, 'Алматы', 'ул. Абая, 44', '#7c3aed', 180, 420],
            ['PLANT', 'Фабрика', 'factory', $plant, 'Капшагай', 'Промзона, 3', '#d97706', 560, 460],
            ['LAKE', 'Коттедж «Озёрный»', 'cottage', $cottage, 'Капшагай', 'Береговая, 7', '#059669', 640, 120],
        ];

        foreach ($sites as [$code, $name, $kind, $domain, $city_, $address, $color, $x, $y]) {
            $this->sites[$code] = Site::firstOrCreate(
                ['code' => $code],
                [
                    'vlan_domain_id' => $domain->id,
                    'name' => $name,
                    'kind' => $kind,
                    'city' => $city_,
                    'address' => $address,
                    'color' => $color,
                    'map_x' => $x,
                    'map_y' => $y,
                ],
            );
        }
    }

    /**
     * Sites exist only after the branches are seeded, so the narrower accounts
     * get theirs here rather than while they are created.
     */
    private function grantSites(): void
    {
        foreach ($this->restricted as $email => $codes) {
            User::firstWhere('email', $email)?->sites()->sync(
                array_map(fn (string $code) => $this->sites[$code]->id, $codes),
            );
        }
    }

    private function vlans(): void
    {
        $plans = [
            'Кампус (Северный + Южный)' => [
                [1, 'default', 'Служебный VLAN по умолчанию'],
                [10, 'voice', 'IP-телефония'],
                [20, 'office-n', 'Рабочие места, Северный'],
                [21, 'office-s', 'Рабочие места, Южный'],
                [30, 'wifi-guest', 'Гостевой Wi-Fi'],
                [40, 'cctv-n', 'Видеонаблюдение, Северный'],
                [41, 'cctv-s', 'Видеонаблюдение, Южный'],
                [100, 'srv', 'Серверы'],
                [110, 'virt', 'Виртуализация и хранилище'],
                [200, 'pos', 'Кассы и терминалы'],
                [300, 'acs', 'СКУД и турникеты'],
                [3000, 'mgmt', 'Управление оборудованием'],
            ],
            'Городской офис' => [
                [1, 'default', 'Служебный VLAN по умолчанию'],
                [10, 'voice', 'IP-телефония'],
                [20, 'office', 'Рабочие места'],
                [30, 'wifi-guest', 'Гостевой Wi-Fi'],
                [3000, 'mgmt', 'Управление оборудованием'],
            ],
            'Фабрика' => [
                [1, 'default', 'Служебный VLAN по умолчанию'],
                [20, 'office', 'Рабочие места'],
                [50, 'wms', 'Склад и терминалы сбора данных'],
                [40, 'cctv', 'Видеонаблюдение'],
                [3000, 'mgmt', 'Управление оборудованием'],
            ],
            'Коттеджи' => [
                [1, 'default', 'Служебный VLAN по умолчанию'],
                [30, 'wifi', 'Беспроводная сеть'],
                [3000, 'mgmt', 'Управление оборудованием'],
            ],
        ];

        $colors = [
            1 => '#64748b', 10 => '#0891b2', 20 => '#0284c7', 21 => '#4f46e5',
            30 => '#65a30d', 40 => '#e11d48', 41 => '#dc2626', 50 => '#d97706',
            100 => '#059669', 110 => '#7c3aed', 200 => '#d97706', 300 => '#e11d48',
            3000 => '#64748b',
        ];

        foreach ($plans as $domainName => $vlans) {
            $domain = VlanDomain::firstWhere('name', $domainName);

            foreach ($vlans as [$vid, $name, $description]) {
                Vlan::firstOrCreate(
                    ['vlan_domain_id' => $domain->id, 'vid' => $vid],
                    ['name' => $name, 'description' => $description, 'color' => $colors[$vid]],
                );
            }
        }
    }

    private function rooms(): void
    {
        $rooms = [
            // site, room, floor, kind, racks: [name, units, kind]
            ['NORTH', 'Серверная', '1 этаж', 'server_room', [
                ['Стойка A', 42, 'rack'],
                ['Стойка B', 42, 'rack'],
            ]],
            ['NORTH', 'Кроссовая 2-го этажа', '2 этаж', 'other', [
                ['Шкаф 2-1', 12, 'wall_cabinet'],
            ]],
            ['SOUTH', 'Серверная', '1 этаж', 'server_room', [
                ['Стойка 1', 42, 'rack'],
            ]],
            ['SOUTH', 'Кроссовая склада', '1 этаж', 'other', [
                ['Шкаф С-1', 15, 'wall_cabinet'],
            ]],
            ['CITY', 'Кроссовая', '3 этаж', 'other', [
                ['Настенный шкаф', 12, 'wall_cabinet'],
            ]],
            ['PLANT', 'Серверная цеха', '1 этаж', 'server_room', [
                ['Стойка цеха', 24, 'rack'],
            ]],
        ];

        foreach ($rooms as $sort => [$siteCode, $name, $floor, $kind, $racks]) {
            $room = Room::firstOrCreate(
                ['site_id' => $this->sites[$siteCode]->id, 'name' => $name],
                ['floor' => $floor, 'kind' => $kind],
            );

            foreach ($racks as $index => [$rackName, $units, $rackKind]) {
                $this->racks["{$siteCode}/{$rackName}"] = Rack::firstOrCreate(
                    ['room_id' => $room->id, 'name' => $rackName],
                    ['u_height' => $units, 'kind' => $rackKind, 'sort' => $sort * 10 + $index],
                );
            }
        }
    }

    private function devices(): void
    {
        $devices = [
            // site, rack, unit, model, name, mgmt ip, colour
            ['NORTH', 'Стойка A', 42, 'Generic|Patch panel 48 x RJ45', 'PP-N-01', null, null],
            ['NORTH', 'Стойка A', 40, 'D-Link|DGS-1210-28/ME', 'SW-N-CORE', '10.40.0.100', '#e11d48'],
            ['NORTH', 'Стойка A', 39, 'D-Link|DGS-1210-12TS/ME', 'SW-N-OPT', '10.40.0.101', '#7c3aed'],
            ['NORTH', 'Стойка A', 38, 'D-Link|DGS-1210-28/ME', 'SW-N-01', '10.40.0.102', null],
            ['NORTH', 'Стойка A', 37, 'D-Link|DGS-1210-28/ME', 'SW-N-02', '10.40.0.103', null],
            ['NORTH', 'Стойка A', 35, 'Kerio|Kerio Control', 'FW-CAMPUS', '10.40.0.1', '#dc2626'],
            ['NORTH', 'Стойка B', 42, 'Generic|Fibre patch panel 12 x LC', 'PP-N-OPT', null, '#0891b2'],
            ['NORTH', 'Стойка B', 40, 'D-Link|DGS-1100-24', 'SW-N-SRV', '10.40.0.104', '#059669'],
            ['NORTH', 'Шкаф 2-1', 12, 'Generic|Patch panel 24 x RJ45', 'PP-N-2F', null, null],
            ['NORTH', 'Шкаф 2-1', 10, 'D-Link|DES-1210-28/ME', 'SW-N-2F', '10.40.0.110', null],

            ['SOUTH', 'Стойка 1', 42, 'Generic|Patch panel 48 x RJ45', 'PP-S-01', null, null],
            ['SOUTH', 'Стойка 1', 40, 'D-Link|DGS-1210-28/ME', 'SW-S-CORE', '10.40.0.120', '#e11d48'],
            ['SOUTH', 'Стойка 1', 39, 'D-Link|DGS-1210-12TS/ME', 'SW-S-OPT', '10.40.0.121', '#7c3aed'],
            ['SOUTH', 'Стойка 1', 38, 'D-Link|DGS-1210-28/ME', 'SW-S-01', '10.40.0.122', null],
            ['SOUTH', 'Шкаф С-1', 15, 'Generic|Patch panel 24 x RJ45', 'PP-S-WH', null, null],
            ['SOUTH', 'Шкаф С-1', 13, 'D-Link|DES-1210-10/ME', 'SW-S-WH', '10.40.0.123', null],

            ['CITY', 'Настенный шкаф', 12, 'Generic|Patch panel 24 x RJ45', 'PP-C-01', null, null],
            ['CITY', 'Настенный шкаф', 10, 'D-Link|DGS-1210-28/ME', 'SW-C-01', '10.40.0.130', null],
            ['CITY', 'Настенный шкаф', 9, 'MikroTik|RouterBOARD (generic)', 'RT-C-01', '10.40.0.131', '#d97706'],

            ['PLANT', 'Стойка цеха', 24, 'Generic|Patch panel 24 x RJ45', 'PP-P-01', null, null],
            ['PLANT', 'Стойка цеха', 22, 'D-Link|DES-1210-28/ME', 'SW-P-01', '10.40.0.140', null],
            ['PLANT', 'Стойка цеха', 21, 'D-Link|DES-1100-24', 'SW-P-02', '10.40.0.141', null],
            ['PLANT', 'Стойка цеха', 20, 'MikroTik|RouterBOARD (generic)', 'RT-P-01', '10.40.0.142', '#d97706'],

            // A cottage has no server room: the router simply sits on a shelf.
            ['LAKE', null, null, 'MikroTik|RouterBOARD (generic)', 'RT-L-01', '10.40.0.150', '#d97706'],
        ];

        $ports = new CreatePortsFromModel;

        foreach ($devices as [$siteCode, $rackName, $unit, $model, $name, $ip, $color]) {
            $site = $this->sites[$siteCode];

            if (Device::where('site_id', $site->id)->where('name', $name)->exists()) {
                continue;
            }

            [$vendor, $modelName] = explode('|', $model);

            $device = Device::create([
                'device_model_id' => DeviceModel::where('vendor', $vendor)
                    ->where('model', $modelName)
                    ->value('id'),
                'site_id' => $site->id,
                'rack_id' => $rackName ? $this->racks["{$siteCode}/{$rackName}"]->id : null,
                'position_u' => $unit,
                'face' => 'front',
                'name' => $name,
                'status' => 'active',
                'mgmt_ip' => $ip,
                'mgmt_url' => $ip ? "http://{$ip}/" : null,
                'color' => $color,
            ]);

            $ports->handle($device);
            $this->describePorts($device);
        }
    }

    /**
     * Fills in what sits on a switch's ports. Real port lists are patchy — some
     * ports are documented, some are free — so this leaves gaps on purpose.
     */
    private function describePorts(Device $device): void
    {
        if ($device->deviceModel->kind !== 'switch') {
            return;
        }

        $workplaces = [
            'Бухгалтерия, каб. 204', 'Бухгалтерия, каб. 204', 'Отдел кадров, каб. 205',
            'Приёмная', 'Кабинет директора', 'Переговорная', 'Отдел продаж, место 1',
            'Отдел продаж, место 2', 'Отдел продаж, место 3', 'Склад, терминал',
            'IP-телефон, ресепшн', 'Камера, вход', 'Камера, парковка',
            'Точка доступа Wi-Fi, холл', 'МФУ, 2 этаж', 'Касса 1', 'Касса 2',
            'Сервер 1С', 'Видеорегистратор', 'Контроллер СКУД',
        ];

        $network = $device->ports()->where('role', 'network')->orderBy('number')->get();

        foreach ($network as $index => $port) {
            // The top pair of ports on these switches is where the uplink goes.
            if ($port->number >= $network->count() - 1) {
                $port->update([
                    'is_uplink' => true,
                    'description' => 'Аплинк в ядро',
                ]);

                continue;
            }

            // Roughly two thirds of the ports are in use, the rest are free.
            if ($index % 3 === 2) {
                continue;
            }

            $port->update(['description' => $workplaces[$index % count($workplaces)]]);
        }
    }
}
