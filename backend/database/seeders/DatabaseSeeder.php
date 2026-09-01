<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    /**
     * The default seed builds a clean, working application: reference data,
     * the role/permission catalogue and the single privileged demo account.
     * Fake fixtures for every other table live in DemoDataSeeder, run on
     * demand via `php artisan db:seed --class=DemoDataSeeder`.
     */
    public function run(): void
    {
        Artisan::call('locations:add');

        $this->call(RolePermissionSeeder::class);
        // Clean reference data (spec 0088, D-3): unlike every other lookup
        // module, the conventional units of measure are seeded here, not from
        // DemoDataSeeder — a production install needs them without ever
        // running the demo fixtures.
        $this->call(UnitOfMeasureSeeder::class);
        $this->call(DemoUserSeeder::class);
    }
}
