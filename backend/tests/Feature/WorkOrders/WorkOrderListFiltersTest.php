<?php

declare(strict_types=1);

use App\Enums\WorkOrderType;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\WorkOrder;
use App\Tables\Shared\ParentCategoryListModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// User request 2026-11-09: the category strip of the Commesse page is replaced
// by advanced filters (parent category, product category, Anagrafica) and the
// page opens in the actor's competence.
// REQUIREMENT CHANGED (user directive 2026-11-12): no "Assegnazione" filter, the
// visibility scope alone decides which commesse an actor sees; "Categoria madre"
// lists and accepts only the parent categories the actor is enabled on; (later the same day) it starts empty,
// and empty means every parent category of the actor.
// REQUIREMENT CHANGED (user directive 2026-11-16): an actor with `work-orders.viewAll` is never limited by the
// competence (an admin with an ISO competence still sees every parent category); the limit applies only without it.

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
    return ['parent_category' => [0]];
}

/**
 * An actor limited to the parent category $root through a competence on one of its children:
 * without `work-orders.viewAll`, which lifts the limit.
 *
 * @return array{0: User, 1: ProductCategory}
 */
function commesseLimitedActor(bool $viewAll = false): array
{
    $actor = categoryTabGridActor('work-orders', viewAll: $viewAll);
    [$function, $root] = competenceVisibilityCategory();
    grantCompetence($actor, $function, ProductCategory::factory()->create(['parent_id' => $root->id, 'business_function_id' => $function->id]));

    return [$actor, $root];
}

it('offers the parent category preset with the other filters, without an assignment filter', function () {
    $actor = categoryTabGridActor('work-orders');
    [$function, $root] = competenceVisibilityCategory();
    $child = ProductCategory::factory()->create(['parent_id' => $root->id, 'business_function_id' => $function->id]);
    grantCompetence($actor, $function, $child);
    Sanctum::actingAs($actor);

    $filters = collect($this->getJson('/api/tables/work-orders/columns')->assertOk()->json('data.advancedFilters'));

    expect($filters->pluck('name')->all())->toBe(['supervisors', 'participants', 'parent_category', 'product_category', 'registry'])
        ->and($filters->firstWhere('name', 'parent_category'))->toMatchArray(['type' => 'relation', 'multiple' => true, 'required' => false, 'sendDefault' => true, 'defaultValue' => []])
        // REQUIREMENT CHANGED (spec 0374 rev.3, D-12): the picker hides the linked legacy roots; (2026-11-12) and lists the actor's roots only.
        ->and($filters->firstWhere('name', 'parent_category')['source'])->toBe(['resource' => 'product-category-roots', 'params' => ['hide_linked_legacy' => 1, 'actor_roots' => ParentCategoryListModule::WorkOrders->value]]);
});

it('does not narrow a request that omits the presets, as the grids inside a record and API clients do', function () {
    $workOrder = categoryTabWorkOrder([ProductCategory::factory()->create()]);
    Sanctum::actingAs(categoryTabGridActor('work-orders'));

    expect(commessePageIds())->toBe([$workOrder->id]);
});

it('starts the parent category empty for every actor', function () {
    [$actor] = commesseLimitedActor();
    Sanctum::actingAs($actor);
    $preset = fn (): array => collect($this->getJson('/api/tables/work-orders/columns')->assertOk()->json('data.advancedFilters'))->firstWhere('name', 'parent_category')['defaultValue'];

    expect($preset())->toBe([]);

    competenceVisibilityProfile($actor)->update(['covers_all_product_categories' => true]);
    app()->forgetScopedInstances();

    expect($preset())->toBe([]);
});

it('lists every commessa an actor with viewAll sees, not only the ones where they hold a role', function () {
    $actor = categoryTabGridActor('work-orders');
    $supervised = WorkOrder::factory()->create(['type' => WorkOrderType::Processing]);
    $supervised->supervisors()->attach($actor->id);
    $other = WorkOrder::factory()->create(['type' => WorkOrderType::Processing]);
    Sanctum::actingAs($actor);

    expect(commessePageIds(personalViewOff()))->toBe([$supervised->id, $other->id]);
});

it('rejects the removed assignment filter', function () {
    Sanctum::actingAs(categoryTabGridActor('work-orders'));

    $this->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 50, 'advancedFilters' => ['assignment' => ['visible']]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['advancedFilters.assignment']);
});

// REQUIREMENT CHANGED (2026-11-20, task 431114): the default parent category (empty, "Tutte" or a pick
// outside the actor's roots) no longer hides a commessa where the actor is Responsabile or Partecipante
// outside their competence (a HACCP commessa assigned to a Sicurezza consultant was unreachable from the
// list); a pick among the actor's roots still narrows to those alone.
it('limits an actor enabled on some parent categories to the branch of those, plus by default the commesse where they hold a role', function () {
    [$actor, $root] = commesseLimitedActor();
    $inBranch = categoryTabWorkOrder([ProductCategory::factory()->create(['parent_id' => $root->id])]);
    $foreignRoot = ProductCategory::factory()->create();
    $supervised = categoryTabWorkOrder([$foreignRoot]);
    $participated = categoryTabWorkOrder([$foreignRoot]);
    categoryTabWorkOrder([$foreignRoot]);
    $inBranch->supervisors()->attach($actor->id);
    $supervised->supervisors()->attach($actor->id);
    $participated->participants()->attach($actor->id, ['position' => 1]);
    Sanctum::actingAs($actor);
    $byDefault = [$inBranch->id, $supervised->id, $participated->id];

    expect(commessePageIds(['parent_category' => []]))->toBe($byDefault)
        ->and(commessePageIds(['parent_category' => [0]]))->toBe($byDefault)
        ->and(commessePageIds(['parent_category' => [$foreignRoot->id]]))->toBe($byDefault)
        ->and(commessePageIds(['parent_category' => [$root->id]]))->toBe([$inBranch->id])
        ->and(commessePageIds(['parent_category' => [$root->id, $foreignRoot->id]]))->toBe([$inBranch->id]);
});

it('does not limit an actor with viewAll, whatever their competence', function () {
    [$actor, $root] = commesseLimitedActor(viewAll: true);
    $inBranch = categoryTabWorkOrder([ProductCategory::factory()->create(['parent_id' => $root->id])]);
    $foreignRoot = ProductCategory::factory()->create();
    $foreign = categoryTabWorkOrder([$foreignRoot]);
    Sanctum::actingAs($actor);
    $pickerIds = collect($this->getJson('/api/product-category-roots/for-select?actor_roots=work-orders')->assertOk()->json('items'))->pluck('id')->all();
    categoryTabSaveFilters('work-orders', ['parent_category' => [$foreignRoot->id]])->assertOk();

    expect(commessePageIds(['parent_category' => []]))->toBe([$inBranch->id, $foreign->id])
        ->and(commessePageIds(['parent_category' => [$foreignRoot->id]]))->toBe([$foreign->id])
        ->and($pickerIds)->toContain($root->id, $foreignRoot->id)
        ->and($this->getJson('/api/tables/work-orders/columns')->assertOk()->json('data.appliedAdvancedFilters'))->toBe(['parent_category' => [$foreignRoot->id]]);
});

it('does not limit an actor enabled on every category', function () {
    $actor = categoryTabGridActor('work-orders');
    grantCompetenceOnAllCategories($actor);
    [$root, $child, , $unrelated] = categoryTabTree();
    $onChild = categoryTabWorkOrder([$child]);
    $elsewhere = categoryTabWorkOrder([$unrelated]);
    Sanctum::actingAs($actor);

    expect(commessePageIds(['parent_category' => []]))->toBe([$onChild->id, $elsewhere->id])
        ->and(commessePageIds(['parent_category' => [0]]))->toBe([$onChild->id, $elsewhere->id])
        ->and(commessePageIds(['parent_category' => [$root->id]]))->toBe([$onChild->id]);
});

it('lists in the parent picker only the roots of a limited actor, and "Tutte" to nobody', function () {
    [$actor, $root] = commesseLimitedActor();
    $foreignRoot = ProductCategory::factory()->create();
    $ids = fn (string $query = ''): array => collect($this->getJson("/api/product-category-roots/for-select?actor_roots=work-orders{$query}")->assertOk()->json('items'))->pluck('id')->all();

    Sanctum::actingAs($actor);
    expect($ids())->toBe([$root->id]);

    $unlimited = User::factory()->create();
    grantCompetenceOnAllCategories($unlimited);
    Sanctum::actingAs($unlimited);
    app()->forgetScopedInstances();
    expect($ids())->toContain($root->id, $foreignRoot->id)->not->toContain(0);
});

it('drops from the saved parent category the roots outside the actor ones', function () {
    [$actor, $root] = commesseLimitedActor();
    $foreignRoot = ProductCategory::factory()->create();
    Sanctum::actingAs($actor);
    $saved = function (array $roots): mixed {
        categoryTabSaveFilters('work-orders', ['parent_category' => $roots])->assertOk();

        return $this->getJson('/api/tables/work-orders/columns')->assertOk()->json('data.appliedAdvancedFilters');
    };

    expect($saved([$root->id, $foreignRoot->id]))->toBe(['parent_category' => [$root->id]])
        ->and($saved([$foreignRoot->id]))->toBeNull();
});

it('drops a saved "Tutte" from the parent category, empty now meaning every root', function () {
    $actor = categoryTabGridActor('work-orders');
    $root = ProductCategory::factory()->create();
    Sanctum::actingAs($actor);
    $saved = function (array $roots): mixed {
        categoryTabSaveFilters('work-orders', ['parent_category' => $roots])->assertOk();

        return $this->getJson('/api/tables/work-orders/columns')->assertOk()->json('data.appliedAdvancedFilters');
    };

    expect($saved([0]))->toBeNull()
        ->and($saved([0, $root->id]))->toBe(['parent_category' => [$root->id]]);
});

it('refuses an empty list on a filter whose default is not empty', function () {
    Sanctum::actingAs(categoryTabGridActor('work-orders'));

    $this->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 50, 'advancedFilters' => ['product_category' => []]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['advancedFilters.product_category']);
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

it('keeps the visibility rule of an actor without viewAll: only the commesse where they hold a role', function () {
    $actor = categoryTabGridActor('work-orders', viewAll: false);
    $own = WorkOrder::factory()->create(['type' => WorkOrderType::Processing]);
    $own->participants()->attach($actor->id, ['position' => 1]);
    WorkOrder::factory()->create(['type' => WorkOrderType::Processing]);
    Sanctum::actingAs($actor);

    expect(commessePageIds(personalViewOff()))->toBe([$own->id]);
});

it('refuses a non-list category', function () {
    Sanctum::actingAs(categoryTabGridActor('work-orders'));

    $this->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 50, 'advancedFilters' => ['parent_category' => 'x']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['advancedFilters.parent_category']);
});

it('does not preset the parent category in the Commesse di Progetto', function () {
    [$actor] = commesseLimitedActor();
    // REQUIREMENT CHANGED (2026-11-25): the page has its own `project-work-orders.viewAny`.
    $actor->givePermissionTo(Permission::findOrCreate('project-work-orders.viewAny'));
    Sanctum::actingAs($actor);

    $parent = collect($this->getJson('/api/tables/project-work-orders/columns')->assertOk()->json('data.advancedFilters'))->firstWhere('name', 'parent_category');

    expect($parent)->not->toHaveKey('defaultValue');
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
