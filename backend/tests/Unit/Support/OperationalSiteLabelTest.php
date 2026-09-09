<?php

declare(strict_types=1);

use App\Models\Address;
use App\Models\City;
use App\Models\OperationalSite;
use App\Support\OperationalSiteLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// Spec 0112, D-8/AC-015 — the ONE definition of a Sede's display label, now
// also consumed by OperationalSiteForSelectResource (which used to carry its
// own byte-identical private copy) and by ReportSiteAvailabilityResolver.

it('composes "{line1} - {city}" when the address has a city (AC-015)', function (): void {
    $address = Address::factory()->create([
        'line1' => 'Via Roma 1',
        'city_id' => City::factory()->create(['name' => 'Frattamaggiore'])->id,
    ])->load('city');

    expect(OperationalSiteLabel::compose($address))->toBe('Via Roma 1 - Frattamaggiore');
});

it('composes line1 alone when the address has no city (AC-015)', function (): void {
    $address = Address::factory()->create(['line1' => 'Via Roma 1', 'city_id' => null])->load('city');

    expect(OperationalSiteLabel::compose($address))->toBe('Via Roma 1');
});

it('composes an empty string when there is no address at all (AC-015)', function (): void {
    expect(OperationalSiteLabel::compose(null))->toBe('');
});

it('leaves the site alias out of the label (AC-015)', function (): void {
    // The alias is a free-text legacy label; the site IS its address, and the
    // for-select's own contract has never shown the alias.
    $site = OperationalSite::factory()->create(['alias' => 'Sede Storica']);
    $site->addresses()->create([
        'line1' => 'Via Roma 1',
        'is_primary' => true,
        'city_id' => City::factory()->create(['name' => 'Aversa'])->id,
    ]);

    expect(OperationalSiteLabel::compose($site->fresh(['addresses.city'])->primaryAddress))
        ->toBe('Via Roma 1 - Aversa');
});
