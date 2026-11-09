<?php

declare(strict_types=1);

use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// User request 2026-11-09: Opportunità, Offerte and Contratti open like Commesse,
// on the actor's own records in the parent categories of their competence.

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $advancedFilters
 * @return array<int, int>
 */
function commercialIds(string $domain, array $advancedFilters = []): array
{
    $items = test()->postJson("/api/tables/{$domain}/rows", ['startRow' => 0, 'endRow' => 50, 'advancedFilters' => $advancedFilters])->assertOk()->json('items');

    return collect($items)->pluck('id')->sort()->values()->all();
}

/** The record of a domain on one category, and the model holding its roles. */
function commercialRecord(string $domain, ProductCategory $category): array
{
    return match ($domain) {
        'opportunities' => (static fn ($record) => [$record, $record])(categoryTabOpportunity($category)),
        'quotes' => (static fn ($record) => [$record, $record])(categoryTabQuote([$category])),
        'contracts' => (static fn ($record) => [$record, $record->quote])(categoryTabContract([$category])),
    };
}

dataset('commercial grids', ['opportunities', 'quotes', 'contracts']);

it('offers the presets first, sent by the page and filled from the competence', function (string $domain) {
    $actor = categoryTabGridActor($domain);
    [$function, $root] = competenceVisibilityCategory();
    grantCompetence($actor, $function, ProductCategory::factory()->create(['parent_id' => $root->id, 'business_function_id' => $function->id]));
    Sanctum::actingAs($actor);

    $filters = collect($this->getJson("/api/tables/{$domain}/columns")->assertOk()->json('data.advancedFilters'));

    expect($filters->pluck('name')->take(2)->all())->toBe(['assignment', 'parent_category'])
        ->and($filters->pluck('name')->all())->toContain('product_category')
        ->and($filters->firstWhere('name', 'assignment'))->toMatchArray(['required' => true, 'sendDefault' => true, 'enumKey' => 'commercial_assignment_scope', 'defaultValue' => ['supervisor', 'manager']])
        ->and($filters->firstWhere('name', 'parent_category'))->toMatchArray(['required' => true, 'sendDefault' => true, 'defaultValue' => [$root->id]]);
})->with('commercial grids');

it('offers "all visible" only to who sees more than their own records', function (string $domain) {
    $excluded = fn (): array => collect($this->getJson("/api/tables/{$domain}/columns")->assertOk()->json('data.advancedFilters'))->firstWhere('name', 'assignment')['excludedValues'];

    Sanctum::actingAs(categoryTabGridActor($domain, viewAll: false));
    expect($excluded())->toBe(['visible']);

    Sanctum::actingAs(categoryTabGridActor($domain));
    expect($excluded())->toBe([]);

    $competent = competenceVisibilityActor($domain);
    [$function, $category] = competenceVisibilityCategory();
    grantCompetence($competent, $function, $category);
    Sanctum::actingAs($competent);
    expect($excluded())->toBe([]);

    Sanctum::actingAs(competenceVisibilityActor($domain));
    expect($excluded())->toBe(['visible']);
})->with('commercial grids');

it('does not narrow a request that omits the presets', function (string $domain) {
    [$record] = commercialRecord($domain, ProductCategory::factory()->create());
    Sanctum::actingAs(categoryTabGridActor($domain));

    expect(commercialIds($domain))->toBe([$record->id]);
})->with('commercial grids');

it('filters by supervisor or account manager, until all visible is picked', function (string $domain) {
    $actor = categoryTabGridActor($domain);
    $category = ProductCategory::factory()->create();
    [$supervised, $supervisedOwner] = commercialRecord($domain, $category);
    $supervisedOwner->update(['supervisor_id' => $actor->id]);
    [$managed, $managedOwner] = commercialRecord($domain, $category);
    $managedOwner->managers()->attach($actor->id, ['position' => 1]);
    [$other] = commercialRecord($domain, $category);
    Sanctum::actingAs($actor);

    expect(commercialIds($domain, ['assignment' => ['supervisor', 'manager']]))->toBe([$supervised->id, $managed->id])
        ->and(commercialIds($domain, ['assignment' => ['supervisor']]))->toBe([$supervised->id])
        ->and(commercialIds($domain, ['assignment' => ['manager']]))->toBe([$managed->id])
        ->and(commercialIds($domain, ['assignment' => ['visible']]))->toBe([$supervised->id, $managed->id, $other->id]);
})->with('commercial grids');

it('filters by parent branch and exact category; "Tutte" lifts the parent filter', function (string $domain) {
    [$root, $child, $grandchild, $unrelated] = categoryTabTree();
    [$onChild] = commercialRecord($domain, $child);
    [$onGrandchild] = commercialRecord($domain, $grandchild);
    [$elsewhere] = commercialRecord($domain, $unrelated);
    Sanctum::actingAs(categoryTabGridActor($domain));

    expect(commercialIds($domain, ['parent_category' => [$root->id]]))->toBe([$onChild->id, $onGrandchild->id])
        ->and(commercialIds($domain, ['product_category' => [$child->id]]))->toBe([$onChild->id])
        ->and(commercialIds($domain, ['parent_category' => [0]]))->toBe([$onChild->id, $onGrandchild->id, $elsewhere->id]);
})->with('commercial grids');

it('filters the offers by client', function () {
    $category = ProductCategory::factory()->create();
    $quote = categoryTabQuote([$category]);
    categoryTabQuote([$category]);
    Sanctum::actingAs(categoryTabGridActor('quotes'));

    expect(commercialIds('quotes', ['registry' => [$quote->opportunity->registry_id]]))->toBe([$quote->id]);
});
