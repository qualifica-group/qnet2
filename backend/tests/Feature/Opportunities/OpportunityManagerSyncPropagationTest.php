<?php

use App\Enums\CategoryManagementMode;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * D-7 bidirectional propagation, the Opportunity -> Quote direction (spec
 * 0087, T-07): a write of `manager_slots` on PATCH /api/opportunities/
 * {opportunity} replicates onto its (at most one) Quote when the category
 * is "sincronizzata" — OpportunityService::propagateManagersToQuote(), a
 * DIRECT sync(), never QuoteManagerWriter (R-2, no re-entrancy).
 *
 * The Quote -> Opportunity direction is already covered at the unit level
 * by QuoteManagerWriterTest (Step 4/INV-4) — not duplicated here.
 */
uses(RefreshDatabase::class);

if (! function_exists('propagationOpportunityActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function propagationOpportunityActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
            Permission::findOrCreate("opportunities.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("opportunities.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('propagationOpportunity')) {
    /**
     * An opportunity whose one covered category satisfies BOTH D-7 settings
     * ("sincronizzata") when $synchronized, or neither otherwise.
     */
    function propagationOpportunity(bool $synchronized): Opportunity
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
            'single_quote_per_opportunity' => $synchronized,
            'management_mode' => $synchronized ? CategoryManagementMode::Single : CategoryManagementMode::Multiple,
        ]);

        $opportunity = Opportunity::factory()->create();
        $opportunity->productLines()->create([
            'business_function_id' => $category->business_function_id,
            'product_category_id' => $category->id,
        ]);

        return $opportunity;
    }
}

it('AC-007: PATCH manager_slots on a synchronized opportunity replaces its Quote\'s GA wholesale', function () {
    $opportunity = propagationOpportunity(synchronized: true);
    $stale = User::factory()->create();
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    $quote->managers()->sync([$stale->id => ['position' => 1]]);
    $fresh = User::factory()->create();
    Sanctum::actingAs(propagationOpportunityActor(['update']));

    $this->patchJson("/api/opportunities/{$opportunity->id}", [
        'manager_slots' => [$fresh->id],
    ])->assertOk();

    $quoteManagers = $quote->managers()->get()->pluck('id')->all();
    expect($quoteManagers)->toBe([$fresh->id])
        ->and($quoteManagers)->not->toContain($stale->id);
});

it('AC-007: propagation also writes the Quote\'s operator_id from the same synced map', function () {
    $opportunity = propagationOpportunity(synchronized: true);
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    $operator = User::factory()->create();
    Sanctum::actingAs(propagationOpportunityActor(['update']));

    $this->patchJson("/api/opportunities/{$opportunity->id}", [
        'manager_slots' => [null, $operator->id],
    ])->assertOk();

    expect($quote->fresh()->operator_id)->toBe($operator->id);
});

it('AC-008: outside a synchronized category, writing the opportunity\'s GA does NOT propagate to its Quote', function () {
    $opportunity = propagationOpportunity(synchronized: false);
    $untouched = User::factory()->create();
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    $quote->managers()->sync([$untouched->id => ['position' => 1]]);
    Sanctum::actingAs(propagationOpportunityActor(['update']));

    $this->patchJson("/api/opportunities/{$opportunity->id}", [
        'manager_slots' => [User::factory()->create()->id],
    ])->assertOk();

    expect($quote->managers()->get()->pluck('id')->all())->toBe([$untouched->id]);
});

it('a synchronized opportunity with no Quote yet is a no-op propagation, not an error', function () {
    $opportunity = propagationOpportunity(synchronized: true);
    $manager = User::factory()->create();
    Sanctum::actingAs(propagationOpportunityActor(['update']));

    $this->patchJson("/api/opportunities/{$opportunity->id}", [
        'manager_slots' => [$manager->id],
    ])->assertOk();

    expect($opportunity->managers()->get()->pluck('id')->all())->toBe([$manager->id]);
});

it('PATCH without manager_slots never triggers propagation, synchronized or not', function () {
    $opportunity = propagationOpportunity(synchronized: true);
    $original = User::factory()->create();
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    $quote->managers()->sync([$original->id => ['position' => 1]]);
    Sanctum::actingAs(propagationOpportunityActor(['update']));

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['general_notes' => 'no-op check'])->assertOk();

    expect($quote->managers()->get()->pluck('id')->all())->toBe([$original->id]);
});
