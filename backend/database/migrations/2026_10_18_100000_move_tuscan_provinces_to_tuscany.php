<?php

use Database\Seeders\ItalianProvinceRegionSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Applies ItalianProvinceRegionSeeder to an install whose geo dataset is
 * already loaded (spec 0189 G-28): eight Tuscan provinces filed under
 * Friuli–Venezia Giulia by world.sql, with their comuni and the addresses
 * stored on them, move to Tuscany.
 *
 * Migrations run BEFORE seeders, so on a fresh install the dataset is not
 * there yet and this is a no-op: DatabaseSeeder runs the same seeder right
 * after `locations:add`.
 *
 * down() is a no-op on purpose: it would put the provinces back under the
 * wrong region, and an address saved after the fix has no previous region to
 * return to.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new ItalianProvinceRegionSeeder)->run();
    }

    public function down(): void {}
};
