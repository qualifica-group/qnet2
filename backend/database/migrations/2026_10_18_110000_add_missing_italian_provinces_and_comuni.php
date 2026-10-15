<?php

use Database\Seeders\ItalianMunicipalitySeeder;
use Database\Seeders\ItalianProvinceRegionSeeder;
use Database\Seeders\ItalianProvinceSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Applies the geo reference fixes to an install whose dataset is already
 * loaded (spec 0189 G-28): the Aosta province with its comuni and addresses
 * (ItalianProvinceSeeder), then the comuni the extract lacks
 * (ItalianMunicipalitySeeder), then the region fix again so the comuni just
 * added under a Tuscan province follow it (ItalianProvinceRegionSeeder).
 *
 * Migrations run BEFORE seeders, so on a fresh install this is a no-op:
 * DatabaseSeeder runs the same seeders right after `locations:add`.
 *
 * down() is a no-op on purpose: an address may already point at a province
 * or comune added here.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new ItalianProvinceSeeder)->run();
        (new ItalianMunicipalitySeeder)->run();
        (new ItalianProvinceRegionSeeder)->run();
    }

    public function down(): void {}
};
