<?php

use App\Models\City;
use App\Models\Company;
use App\Models\CompanySite;
use Database\Seeders\DemoCompanySiteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds sites for the seeded companies, with a card and address on each', function (): void {
    City::factory()->count(10)->create();
    Company::factory()->count(4)->create();

    test()->seed(DemoCompanySiteSeeder::class);

    expect(CompanySite::count())->toBeGreaterThan(0);

    CompanySite::query()->with('personalData')->get()->each(function (CompanySite $site): void {
        expect($site->personalData)->not->toBeNull();
    });
});
