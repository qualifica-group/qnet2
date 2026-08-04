<?php

use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\Opportunities\OpportunityManagerLabelResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0080, user directive 2026-08-04 decision 1: the "Gestore Account"
// label overrides an Opportunity resolves from its product line(s)'
// category(ies) — AC-020..AC-023, plus the additive OpportunityResource field.

uses(RefreshDatabase::class);

if (! function_exists('managerLabelOpportunityUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function managerLabelOpportunityUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("opportunities.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("opportunities.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// OpportunityManagerLabelResolver (unit-level, AC-020..AC-023)
// ---------------------------------------------------------------------------

it('AC-020: a single product line resolves to that category\'s effective labels', function (): void {
    $category = ProductCategory::factory()->create(['manager_labels' => ['1' => 'Commerciale', '2' => 'Operatore']]);
    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $category->id]);
    $opportunity->load('productLines.productCategory');

    expect(app(OpportunityManagerLabelResolver::class)->resolve($opportunity))
        ->toBe(['1' => 'Commerciale', '2' => 'Operatore']);
});

it('AC-021: two product lines resolving to DIFFERENT labels -> []', function (): void {
    $categoryA = ProductCategory::factory()->create(['manager_labels' => ['2' => 'Operatore']]);
    $categoryB = ProductCategory::factory()->create(['manager_labels' => ['2' => 'Consulente']]);
    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $categoryA->id]);
    OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $categoryB->id]);
    $opportunity->load('productLines.productCategory');

    expect(app(OpportunityManagerLabelResolver::class)->resolve($opportunity))->toBe([]);
});

it('AC-022: two DIFFERENT categories resolving to IDENTICAL labels are not a conflict', function (): void {
    $categoryA = ProductCategory::factory()->create(['manager_labels' => ['2' => 'Operatore']]);
    $categoryB = ProductCategory::factory()->create(['manager_labels' => ['2' => 'Operatore']]);
    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $categoryA->id]);
    OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $categoryB->id]);
    $opportunity->load('productLines.productCategory');

    expect(app(OpportunityManagerLabelResolver::class)->resolve($opportunity))->toBe(['2' => 'Operatore']);
});

it('AC-022 regression: identical content resolved via DIFFERENT key-insertion order is still not a conflict', function (): void {
    // Category A inherits GA2 from its parent and defines GA1 itself — the
    // per-position merge inserts key 2 (from the ancestor) BEFORE key 1
    // (from A's own row). Category B defines both itself in one shot, JSON
    // insertion order [1, 2]. Same resolved CONTENT, different key order —
    // PHP's `===` on arrays is order-sensitive, so a naive comparison would
    // wrongly call this a conflict; CategoryManagerLabelResolver::
    // effectiveManagerLabels() ksort()s before returning specifically to
    // keep this comparison order-independent.
    $parentOfA = ProductCategory::factory()->create(['manager_labels' => ['2' => 'Operatore']]);
    $categoryA = ProductCategory::factory()->childOf($parentOfA)->create(['manager_labels' => ['1' => 'Commerciale']]);
    $categoryB = ProductCategory::factory()->create(['manager_labels' => ['1' => 'Commerciale', '2' => 'Operatore']]);

    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $categoryA->id]);
    OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $categoryB->id]);
    $opportunity->load('productLines.productCategory');

    expect(app(OpportunityManagerLabelResolver::class)->resolve($opportunity))
        ->toBe(['1' => 'Commerciale', '2' => 'Operatore']);
});

it('AC-023: an opportunity with no product line -> []', function (): void {
    $opportunity = Opportunity::factory()->create();
    $opportunity->load('productLines.productCategory');

    expect(app(OpportunityManagerLabelResolver::class)->resolve($opportunity))->toBe([]);
});

// ---------------------------------------------------------------------------
// OpportunityResource — additive field, manager_slots/managers untouched
// ---------------------------------------------------------------------------

it('show: manager_labels is additive and does not disturb managers/manager_slots', function (): void {
    $actor = managerLabelOpportunityUserWith(['view']);
    $category = ProductCategory::factory()->create(['manager_labels' => ['1' => 'Commerciale']]);
    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $category->id]);
    $manager = User::factory()->create();
    $opportunity->managers()->attach($manager->id, ['position' => 1]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/opportunities/{$opportunity->id}")->assertOk();

    expect($response->json('data.manager_labels'))->toBe(['1' => 'Commerciale'])
        ->and($response->json('data.managers.0.id'))->toBe($manager->id)
        ->and($response->json('data.managers.0.position'))->toBe(1);
});

it('show: manager_labels is [] when the opportunity has no product line', function (): void {
    $actor = managerLabelOpportunityUserWith(['view']);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/opportunities/{$opportunity->id}")
        ->assertOk()
        ->assertJsonPath('data.manager_labels', []);
});

it('AC-053: a label configured beyond the 4th position (spec 0080 amendment A1) surfaces here exactly like the first four', function (): void {
    $actor = managerLabelOpportunityUserWith(['view']);
    $category = ProductCategory::factory()->create(['manager_labels' => ['5' => 'GA Cinque']]);
    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $category->id]);
    $manager = User::factory()->create();
    $opportunity->managers()->attach($manager->id, ['position' => 5]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/opportunities/{$opportunity->id}")
        ->assertOk()
        ->assertJsonPath('data.manager_labels.5', 'GA Cinque')
        ->assertJsonPath('data.managers.0.position', 5);
});
