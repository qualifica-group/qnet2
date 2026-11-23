<?php

declare(strict_types=1);

use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// User request 2026-11-09: Opportunità, Offerte and Contratti open like Commesse,
// in the parent categories of the actor's competence.
// REQUIREMENT CHANGED (user directive 2026-11-12): no "Assegnazione" filter, the
// visibility scope alone decides which records an actor sees; "Categoria madre"
// lists and accepts only the parent categories the actor is enabled on.
// REQUIREMENT CHANGED (user directive 2026-11-16): an actor with `<module>.viewAll` is never limited by the
// competence; the limit applies only without it.

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

it('offers the parent category first, empty and sent by the page', function (string $domain) {
    $actor = categoryTabGridActor($domain);
    [$function, $root] = competenceVisibilityCategory();
    grantCompetence($actor, $function, ProductCategory::factory()->create(['parent_id' => $root->id, 'business_function_id' => $function->id]));
    Sanctum::actingAs($actor);

    $filters = collect($this->getJson("/api/tables/{$domain}/columns")->assertOk()->json('data.advancedFilters'));

    expect($filters->pluck('name')->first())->toBe('parent_category')
        ->and($filters->pluck('name')->all())->toContain('product_category')->not->toContain('assignment')
        ->and($filters->firstWhere('name', 'parent_category'))->toMatchArray(['required' => false, 'sendDefault' => true, 'defaultValue' => []])
        ->and($filters->firstWhere('name', 'parent_category')['source']['params'])->toMatchArray(['actor_roots' => $domain]);
})->with('commercial grids');

it('does not narrow a request that omits the presets', function (string $domain) {
    [$record] = commercialRecord($domain, ProductCategory::factory()->create());
    Sanctum::actingAs(categoryTabGridActor($domain));

    expect(commercialIds($domain))->toBe([$record->id]);
})->with('commercial grids');

it('lists every record the actor sees, without an assignment filter', function (string $domain) {
    $actor = categoryTabGridActor($domain);
    $category = ProductCategory::factory()->create();
    [$supervised, $supervisedOwner] = commercialRecord($domain, $category);
    $supervisedOwner->update(['supervisor_id' => $actor->id]);
    [$other] = commercialRecord($domain, $category);
    Sanctum::actingAs($actor);

    expect(commercialIds($domain, ['parent_category' => [0]]))->toBe([$supervised->id, $other->id]);

    $this->postJson("/api/tables/{$domain}/rows", ['startRow' => 0, 'endRow' => 50, 'advancedFilters' => ['assignment' => ['visible']]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['advancedFilters.assignment']);
})->with('commercial grids');

// REQUIREMENT CHANGED (user directive 2026-11-20): on Opportunità and Offerte the default parent category no
// longer hides a record where the actor is in the team (Supervisore or Gestore account) outside their
// competence; a pick among the actor's roots still narrows to those alone. Contratti unchanged.
it('limits an actor without viewAll to their parent categories, plus by default the records where they are in the team', function (string $domain) {
    $actor = categoryTabGridActor($domain, viewAll: false);
    [$function, $root] = competenceVisibilityCategory();
    $child = ProductCategory::factory()->create(['parent_id' => $root->id, 'business_function_id' => $function->id]);
    grantCompetence($actor, $function, $child);
    $foreignRoot = ProductCategory::factory()->create();
    [$inBranch] = commercialRecord($domain, $child);
    [$supervised] = commercialRecord($domain, $foreignRoot);
    [$managed] = commercialRecord($domain, $foreignRoot);
    commercialRecord($domain, $foreignRoot);
    $inBranch->update(['supervisor_id' => $actor->id]);
    $supervised->update(['supervisor_id' => $actor->id]);
    $managed->managers()->attach($actor->id, ['position' => 1]);
    Sanctum::actingAs($actor);
    $byDefault = [$inBranch->id, $supervised->id, $managed->id];

    expect(commercialIds($domain, ['parent_category' => []]))->toBe($byDefault)
        ->and(commercialIds($domain, ['parent_category' => [0]]))->toBe($byDefault)
        ->and(commercialIds($domain, ['parent_category' => [$foreignRoot->id]]))->toBe($byDefault)
        ->and(commercialIds($domain, ['parent_category' => [$root->id]]))->toBe([$inBranch->id])
        ->and(commercialIds($domain, ['parent_category' => [$root->id, $foreignRoot->id]]))->toBe([$inBranch->id]);
})->with(['opportunities', 'quotes']);

it('limits an actor without viewAll enabled on some parent categories to those, whatever is picked', function (string $domain) {
    $actor = categoryTabGridActor($domain, viewAll: false);
    [$function, $root] = competenceVisibilityCategory();
    $child = ProductCategory::factory()->create(['parent_id' => $root->id, 'business_function_id' => $function->id]);
    grantCompetence($actor, $function, $child);
    $foreignRoot = ProductCategory::factory()->create();
    [$inBranch, $inBranchOwner] = commercialRecord($domain, $child);
    [, $foreignOwner] = commercialRecord($domain, $foreignRoot);
    // Without viewAll the actor sees only the records they supervise: both, so only the category limit narrows.
    $inBranchOwner->update(['supervisor_id' => $actor->id]);
    $foreignOwner->update(['supervisor_id' => $actor->id]);
    Sanctum::actingAs($actor);

    expect(commercialIds($domain, ['parent_category' => []]))->toBe([$inBranch->id])
        ->and(commercialIds($domain, ['parent_category' => [$root->id]]))->toBe([$inBranch->id])
        ->and(commercialIds($domain, ['parent_category' => [0]]))->toBe([$inBranch->id])
        ->and(commercialIds($domain, ['parent_category' => [$foreignRoot->id]]))->toBe([$inBranch->id]);
})->with(['contracts']);

it('does not limit an actor with viewAll, whatever their competence', function (string $domain) {
    $actor = categoryTabGridActor($domain);
    [$function, $root] = competenceVisibilityCategory();
    $child = ProductCategory::factory()->create(['parent_id' => $root->id, 'business_function_id' => $function->id]);
    grantCompetence($actor, $function, $child);
    $foreignRoot = ProductCategory::factory()->create();
    [$inBranch] = commercialRecord($domain, $child);
    [$foreign] = commercialRecord($domain, $foreignRoot);
    Sanctum::actingAs($actor);
    $pickerIds = collect($this->getJson("/api/product-category-roots/for-select?actor_roots={$domain}")->assertOk()->json('items'))->pluck('id')->all();

    expect(commercialIds($domain, ['parent_category' => []]))->toBe([$inBranch->id, $foreign->id])
        ->and(commercialIds($domain, ['parent_category' => [$foreignRoot->id]]))->toBe([$foreign->id])
        ->and($pickerIds)->toContain($root->id, $foreignRoot->id);
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
