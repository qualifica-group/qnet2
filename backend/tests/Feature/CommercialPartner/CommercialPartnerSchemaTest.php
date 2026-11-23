<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\Registry;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0417 AC-001: `commercial_partner_id` sits next to `commercial_id` with
 * the same FK discipline per table.
 */
uses(RefreshDatabase::class);

it('adds a nullable commercial_partner_id to registries, opportunities and quotes', function (string $table) {
    expect(Schema::hasColumn($table, 'commercial_partner_id'))->toBeTrue();
})->with(['registries', 'opportunities', 'quotes']);

it('exposes the commercial partner relation on the three models', function () {
    $partner = Referent::factory()->create();
    $registry = Registry::factory()->create(['commercial_partner_id' => $partner->id]);
    $opportunity = Opportunity::factory()->create(['registry_id' => $registry->id, 'commercial_partner_id' => $partner->id]);
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id, 'commercial_partner_id' => $partner->id]);

    expect($registry->commercialPartner->is($partner))->toBeTrue()
        ->and($opportunity->commercialPartner->is($partner))->toBeTrue()
        ->and($quote->commercialPartner->is($partner))->toBeTrue()
        ->and($partner->opportunitiesAsCommercialPartner()->pluck('id')->all())->toBe([$opportunity->id]);
});

it('clears the registry partner when the referent is deleted', function () {
    $partner = Referent::factory()->create();
    $registry = Registry::factory()->create(['commercial_partner_id' => $partner->id]);

    $partner->delete();

    expect($registry->fresh()->commercial_partner_id)->toBeNull();
});

it('refuses to delete a referent that is the partner of a quote', function () {
    $partner = Referent::factory()->create();
    Quote::factory()->create(['commercial_partner_id' => $partner->id]);

    expect(fn () => $partner->delete())->toThrow(QueryException::class);
});
