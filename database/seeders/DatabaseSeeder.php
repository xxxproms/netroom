<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Everything a fresh install needs: the permission set, the default roles
     * and a starting device catalogue.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            DeviceModelSeeder::class,
        ]);
    }
}
