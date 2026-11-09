<?php

declare(strict_types=1);

use App\Enums\WorkOrderType;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// User request 2026-11-09: the category strip of the Commesse page is replaced
// by advanced filters (parent category, product category, Anagrafica) and the
// page opens on the actor's own commesse in the actor's competence.

uses(RefreshDatabase::class);

/**
 * The Commesse page's own request: `processing` type.
 *
 * @param  array<string, mixed>  $advancedFilters
 * @return array<int, int>
 */
function commessePageIds(array $advancedFilters = []): array
{
    $items = test()->postJson('/api/tables/work-orders/rows', [
        'startRow' => 0,
        'endRow' => 50,
        'work_order_type' => WorkOrderType::Processing->value,
        'advancedFilters' => $advancedFilters,
    ])->assertOk()->json('items');

    return collect($items)->pluck('id')->sort()->values()->all();
}

/** @return array<string, mixed> */
function personalViewOff(): array
{
    return ['assignment' => ['visible'], 'parent_category' => [0]];
}

it('offers assignment and parent category preset like the Task assignment, then the other filters', function () {
    $actor = categoryTabGridActor('work-orders');
    [$function, $root] = competenceVisibilityCategory();
    $child = ProductCategory::factory()->create(['parent_id' => $root->id, 'business_function_id' => $function->id]);
    grantCompetence($actor, $function, $child);
    Sanctum::actingAs($actor);

    $filters = collect($this->getJson('/api/tables/work-orders/columns')->assertOk()->json('data.advancedFilters'));

    expect($filters->pluck('name')->all())->toBe(['assignment', 'supervisors', 'participants', 'parent_category', 'product_category', 'registry'])
        ->and($filters->firstWhere('name', 'assignment'))->toMatchArray(['type' => 'enum', 'multiple' => true, 'required' => true, 'sendDefault' => true, 'enumKey' => 'work_order_assignment_scope', 'defaultValue' => ['supervisor', 'participant']])
        ->and($filters->firstWhere('name', 'parent_category'))->toMatchArray(['type' => 'relation', 'multiple' => true, 'required' => true, 'sendDefault' => true, 'defaultValue' => [$root->id]])
        ->and($filters->firstWhere('name', 'parent_category')['source'])->toBe(['resource' => 'product-category-roots']);
});

it('offers "all visible" only to who sees more than their own commesse', function () {
    $excluded = fn (): array => collect($this->getJson('/api/tables/work-orders/columns')->assertOk()->json('data.advancedFilters'))->firstWhere('name', 'assignment')['excludedValues'];

    Sanctum::actingAs(categoryTabGridActor('work-orders', viewAll: false));
    expect($excluded())->toBe(['visible']);

    Sanctum::actingAs(categoryTabGridActor('work-orders'));
    expect($excluded())->toBe([]);
});

it('does not narrow a request that omits the presets, as the grids inside a record and API clients do', function () {
    $workOrder = categoryTabWorkOrder([ProductCategory::factory()->create()]);
    Sanctum::actingAs(categoryTabGridActor('work-orders'));

    expect(commessePageIds())->toBe([$workOrder->id]);
});

it('presets "Tutte" for an actor without competence or with every category', function () {
    $actor = categoryTabGridActor('work-orders');
    Sanctum::actingAs($actor);
    $preset = fn (): array => collect($this->getJson('/api/tables/work-orders/columns')->assertOk()->json('data.advancedFilters'))->firstWhere('name', 'parent_category')['defaultValue'];

    expect($preset())->toBe([0]);

    competenceVisibilityProfile($actor)->update(['covers_all_product_categories' => true]);
    app()->forgetScopedInstances();

    expect($preset())->toBe([0]);
});

it('opens on the commesse where the actor is a supervisor or a participant, even with viewAll', function () {
    $actor = categoryTabGridActor('work-orders');
    $supervised = WorkOrder::factory()->create(['type' => WorkOrderType::Processing]);
    $supervised->supervisors()->attach($actor->id);
    $joined = WorkOrder::factory()->create(['type' => WorkOrderType::Processing]);
    $joined->participants()->attach($actor->id, ['position' => 1]);
    $other = WorkOrder::factory()->create(['type' => WorkOrderType::Processing]);
    Sanctum::actingAs($actor);

    expect(commessePageIds(['assignment' => ['supervisor', 'participant']]))->toBe([$supervised->id, $joined->id])
        ->and(commessePageIds(['assignment' => ['supervisor']]))->toBe([$supervised->id])
        ->and(commessePageIds(['assignment' => ['participant']]))->toBe([$joined->id])
        ->and(commessePageIds(['assignment' => ['visible']]))->toBe([$supervised->id, $joined->id, $other->id]);
});

it('opens on the whole branch of the competence parents, in AND with the commesse of the actor, until "Tutte" is picked', function () {
    $actor = categoryTabGridActor('work-orders');
    [$function, $root] = competenceVisibilityCategory();
    $competent = ProductCategory::factory()->create(['parent_id' => $root->id, 'business_function_id' => $function->id]);
    $sibling = ProductCategory::factory()->create(['parent_id' => $root->id, 'business_function_id' => $function->id]);
    grantCompetence($actor, $function, $competent);
    $foreignRoot = ProductCategory::factory()->create();
    $inBranch = categoryTabWorkOrder([$sibling]);
    $inBranch->supervisors()->attach($actor->id);
    $outOfBranch = categoryTabWorkOrder([$foreignRoot]);
    $outOfBranch->supervisors()->attach($actor->id);
    $notMine = categoryTabWorkOrder([$competent]);
    Sanctum::actingAs($actor);

    $mine = ['assignment' => ['supervisor', 'participant']];

    expect(commessePageIds([...$mine, 'parent_category' => [$root->id]]))->toBe([$inBranch->id])
        ->and(commessePageIds([...$mine, 'parent_category' => [0]]))->toBe([$inBranch->id, $outOfBranch->id])
        ->and(commessePageIds([...$mine, 'parent_category' => [$foreignRoot->id]]))->toBe([$outOfBranch->id])
        ->and(commessePageIds(['assignment' => ['visible'], 'parent_category' => [$root->id]]))->toBe([$inBranch->id, $notMine->id]);
});

it('filters by parent category through the whole branch and by product category on that category alone', function () {
    [$root, $child, $grandchild, $unrelated] = categoryTabTree();
    $onChild = categoryTabWorkOrder([$child]);
    $onGrandchild = categoryTabWorkOrder([$grandchild]);
    categoryTabWorkOrder([$unrelated]);
    categoryTabWorkOrder([$child], cost: true);
    Sanctum::actingAs(categoryTabGridActor('work-orders'));

    expect(commessePageIds([...personalViewOff(), 'parent_category' => [$root->id]]))->toBe([$onChild->id, $onGrandchild->id])
        ->and(commessePageIds([...personalViewOff(), 'product_category' => [$child->id]]))->toBe([$onChild->id]);
});

it('filters by registry, reaching a multi-offer commessa through the offers of its lines', function () {
    $category = ProductCategory::factory()->create();
    $mono = categoryTabWorkOrder([$category]);
    $picked = categoryTabQuote([$category]);
    $multi = categoryTabMultiWorkOrder($picked, categoryTabQuote([$category]));
    categoryTabWorkOrder([$category]);
    Sanctum::actingAs(categoryTabGridActor('work-orders'));

    expect(commessePageIds([...personalViewOff(), 'registry' => [$mono->quote->opportunity->registry_id, $picked->opportunity->registry_id]]))
        ->toBe([$mono->id, $multi->id]);
});

it('keeps the visibility rule of an actor without viewAll when the presets are lifted', function () {
    $actor = categoryTabGridActor('work-orders', viewAll: false);
    $own = WorkOrder::factory()->create(['type' => WorkOrderType::Processing]);
    $own->participants()->attach($actor->id, ['position' => 1]);
    WorkOrder::factory()->create(['type' => WorkOrderType::Processing]);
    Sanctum::actingAs($actor);

    expect(commessePageIds(personalViewOff()))->toBe([$own->id]);
});

it('refuses a non-list assignment and a non-list category', function () {
    Sanctum::actingAs(categoryTabGridActor('work-orders'));

    $this->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 50, 'advancedFilters' => ['assignment' => 'visible', 'parent_category' => 'x']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['advancedFilters.assignment', 'advancedFilters.parent_category']);
});

it('does not add the personal view to the Commesse di Progetto', function () {
    Sanctum::actingAs(categoryTabGridActor('work-orders'));

    $names = array_column($this->getJson('/api/tables/project-work-orders/columns')->assertOk()->json('data.advancedFilters'), 'name');

    expect($names)->not->toContain('assignment');
});

it('lists the first-level categories for the parent filter, containers and inactive ones included', function () {
    $this->getJson('/api/product-category-roots/for-select')->assertUnauthorized();

    $container = ProductCategory::factory()->create(['name' => 'ISO', 'is_selectable' => false]);
    $inactive = ProductCategory::factory()->create(['name' => 'Retired', 'is_active' => false]);
    $child = ProductCategory::factory()->create(['name' => 'ISO 9001', 'parent_id' => $container->id]);
    Sanctum::actingAs(User::factory()->create());

    $ids = collect($this->getJson('/api/product-category-roots/for-select')->assertOk()->json('items'))->pluck('id');

    expect($ids)->toContain($container->id, $inactive->id)->and($ids)->not->toContain($child->id);
});

it('leads the first page of the parent picker with "Tutte", also when typed, without counting it', function () {
    ProductCategory::factory()->create(['name' => 'ISO']);
    ProductCategory::factory()->create(['name' => 'GDPR']);
    Sanctum::actingAs(User::factory()->create());

    $first = $this->getJson('/api/product-category-roots/for-select')->assertOk();
    $labels = fn (string $query): array => collect($this->getJson("/api/product-category-roots/for-select?{$query}")->assertOk()->json('items'))->pluck('id')->all();

    expect($first->json('items.0.id'))->toBe(0)
        ->and($first->json('pagination.total'))->toBe(2)
        ->and($labels('search=tutti'))->toBe([0])
        ->and($labels('search=tutte'))->toBe([0])
        ->and($labels('search=ISO'))->not->toContain(0)
        ->and($labels('offset=1'))->not->toContain(0)
        ->and($labels('search=ISO&ids[]=0'))->toContain(0);
});
