<?php

use App\Models\WorkOrder;
use App\Services\WorkOrders\WorkOrderVisibilityScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * The `work-orders.viewCompetence` tier (spec 0340): a commessa is of
 * competence when ANY of its source offers carries a REVENUE line in the
 * actor's reach (mono-offer `quote_id` or the pivot lines of a multi-offer one).
 */
uses(RefreshDatabase::class);

function competenceWorkOrderIds(): array
{
    return collect(test()->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 100])->assertOk()->json('items'))
        ->pluck('id')->sort()->values()->all();
}

function competenceWorkOrderQueryCount(): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 100])->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('AC-007: a multi-offer commessa is of competence when one source offer has revenue in reach; others stay 403', function () {
    [$function, $covered] = competenceVisibilityCategory();
    [, $foreign] = competenceVisibilityCategory();
    $multi = categoryTabMultiWorkOrder(categoryTabQuote([$covered]), categoryTabQuote([$foreign]));
    $mono = categoryTabWorkOrder([$covered]);
    $outside = categoryTabMultiWorkOrder(categoryTabQuote([$foreign]));
    $actor = competenceVisibilityActor('work-orders');
    grantCompetence($actor, $function, $covered);
    Sanctum::actingAs($actor);

    expect(competenceWorkOrderIds())->toBe(collect([$multi->id, $mono->id])->sort()->values()->all());
    $this->getJson("/api/work-orders/{$multi->id}")->assertOk();
    $this->getJson("/api/work-orders/{$outside->id}", ['Accept-Language' => 'it'])->assertForbidden()
        ->assertJsonPath('message', trans('authorization.work_order_out_of_scope', [], 'it'));
});

it('AC-002: without the ability the competent commessa is absent and the show is 403', function () {
    [$function, $covered] = competenceVisibilityCategory();
    $workOrder = categoryTabWorkOrder([$covered]);
    $actor = competenceVisibilityActor('work-orders', ['viewAny', 'view']);
    grantCompetence($actor, $function, $covered);
    Sanctum::actingAs($actor);

    expect(competenceWorkOrderIds())->toBe([]);
    $this->getJson("/api/work-orders/{$workOrder->id}")->assertForbidden();
});

it('AC-004: a COST-only category does not make the commessa of competence', function () {
    [$function, $covered] = competenceVisibilityCategory();
    categoryTabWorkOrder([$covered], cost: true);
    $actor = competenceVisibilityActor('work-orders');
    grantCompetence($actor, $function, $covered);
    Sanctum::actingAs($actor);

    expect(competenceWorkOrderIds())->toBe([]);
});

it('AC-005: a commessa with no revenue lines is visible only with the all-categories flag', function () {
    $bare = WorkOrder::factory()->create(['quote_id' => null]);
    [$function, $covered] = competenceVisibilityCategory();

    $scoped = competenceVisibilityActor('work-orders');
    grantCompetence($scoped, $function, $covered);
    Sanctum::actingAs($scoped);
    expect(competenceWorkOrderIds())->toBe([]);

    $wildcard = competenceVisibilityActor('work-orders');
    grantCompetenceOnAllCategories($wildcard);
    Sanctum::actingAs($wildcard);
    app()->forgetScopedInstances();
    expect(competenceWorkOrderIds())->toBe([$bare->id]);
});

it('AC-012: viewCompetence without competence adds nothing', function () {
    categoryTabWorkOrder([competenceVisibilityCategory()[1]]);
    Sanctum::actingAs(competenceVisibilityActor('work-orders'));

    expect(competenceWorkOrderIds())->toBe([]);
});

it('AC-013: the tier does not grant update or delete on a commessa', function () {
    [$function, $covered] = competenceVisibilityCategory();
    $workOrder = categoryTabWorkOrder([$covered]);
    $viewer = competenceVisibilityActor('work-orders');
    grantCompetence($viewer, $function, $covered);
    Sanctum::actingAs($viewer);

    $this->deleteJson("/api/work-orders/{$workOrder->id}")->assertForbidden();
    $this->patchJson("/api/work-orders/{$workOrder->id}", ['title' => 'x'])->assertForbidden();

    $editor = competenceVisibilityActor('work-orders', ['viewAny', 'view', 'viewCompetence', 'update', 'delete']);
    grantCompetence($editor, $function, $covered);
    Sanctum::actingAs($editor);
    app()->forgetScopedInstances();
    expect($this->patchJson("/api/work-orders/{$workOrder->id}", ['title' => 'x'])->status())->not->toBe(403);
    expect($this->deleteJson("/api/work-orders/{$workOrder->id}")->status())->not->toBe(403);
});

it('AC-014: own and competence tiers are a union that still obeys the grid filter', function () {
    [$function, $covered] = competenceVisibilityCategory();
    $actor = competenceVisibilityActor('work-orders');
    grantCompetence($actor, $function, $covered);
    $own = WorkOrder::factory()->create(['title' => 'keep-own']);
    $own->supervisors()->attach($actor->id);
    $competent = categoryTabWorkOrder([$covered]);
    $competent->update(['title' => 'keep-comp']);
    categoryTabWorkOrder([$covered])->update(['title' => 'drop']);
    Sanctum::actingAs($actor);

    $ids = collect($this->postJson('/api/tables/work-orders/rows', [
        'startRow' => 0, 'endRow' => 100,
        'filterModel' => ['title' => ['filterType' => 'text', 'type' => 'startsWith', 'filter' => 'keep']],
    ])->assertOk()->json('items'))->pluck('id')->sort()->values()->all();

    expect($ids)->toBe(collect([$own->id, $competent->id])->sort()->values()->all());
});

it('AC-016: the row query count does not grow with the competence-only commesse', function () {
    [$function, $covered] = competenceVisibilityCategory();
    $actor = competenceVisibilityActor('work-orders');
    grantCompetence($actor, $function, $covered);
    Sanctum::actingAs($actor);
    competenceWorkOrderQueryCount(); // warm-up: permission and competence caches are filled once

    foreach (range(1, 5) as $unused) {
        categoryTabWorkOrder([$covered]);
    }
    $withFive = competenceWorkOrderQueryCount();

    foreach (range(1, 15) as $unused) {
        categoryTabWorkOrder([$covered]);
    }
    $withTwenty = competenceWorkOrderQueryCount();

    expect(competenceWorkOrderIds())->toHaveCount(20)->and($withTwenty)->toBe($withFive);
});

it('AC-020: a null actor matches no commessa', function () {
    categoryTabWorkOrder([competenceVisibilityCategory()[1]]);

    expect(WorkOrderVisibilityScope::scopeToActor(WorkOrder::query(), null)->count())->toBe(0);
});

it('AC-016: projects the competence verdict only for an actor it can decide', function () {
    [$function, $covered] = competenceVisibilityCategory();
    $bounded = competenceVisibilityActor('work-orders');
    grantCompetence($bounded, $function, $covered);
    $seesAll = competenceVisibilityActor('work-orders', ['viewAny', 'view', 'viewCompetence', 'viewAll']);
    grantCompetence($seesAll, $function, $covered);

    expect(WorkOrderVisibilityScope::withCompetenceVerdict(WorkOrder::query(), $bounded)->toSql())
        ->toContain(WorkOrderVisibilityScope::COMPETENCE_VERDICT_ATTRIBUTE)
        ->and(WorkOrderVisibilityScope::withCompetenceVerdict(WorkOrder::query(), $seesAll)->toSql())
        ->not->toContain(WorkOrderVisibilityScope::COMPETENCE_VERDICT_ATTRIBUTE);
});

it('AC-016: an actor who sees every commessa still gets rows outside the competence', function () {
    [$function, $covered] = competenceVisibilityCategory();
    $inside = categoryTabWorkOrder([$covered]);
    $outside = categoryTabWorkOrder([competenceVisibilityCategory()[1]]);
    $actor = competenceVisibilityActor('work-orders', ['viewAny', 'view', 'viewCompetence', 'viewAll']);
    grantCompetence($actor, $function, $covered);
    Sanctum::actingAs($actor);

    expect(competenceWorkOrderIds())->toBe([$inside->id, $outside->id]);
});
