<?php

use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\WorkOrder;
use App\Tables\CategoryTabs\WorkOrderSourceQuotes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0431 (AC-006/AC-007): a commessa born without an offer is listed,
 * filtered, searched and sorted by its own client, found from its client's
 * registry, seen through its own operational site and categories.
 */
function standaloneGridRows(array $body = []): array
{
    return test()->postJson('/api/tables/project-work-orders/rows', ['startRow' => 0, 'endRow' => 50, ...$body])
        ->assertOk()->json('items');
}

function standaloneOfferWorkOrder(string $clientName): WorkOrder
{
    $quote = Quote::factory()->create();
    $quote->opportunity->registry->update(['name' => $clientName]);

    return WorkOrder::factory()->create(['quote_id' => $quote->id, 'type' => 'project']);
}

it('lists the own client in the project grid, filters, searches and sorts by it (AC-006)', function () {
    Sanctum::actingAs(projectWorkOrderViewer());
    $standalone = standaloneProjectWorkOrder(['registry_id' => Registry::factory()->create(['name' => 'Zeta Interna'])->id]);
    $fromOffer = standaloneOfferWorkOrder('Alfa Clienti');

    $rows = collect(standaloneGridRows())->keyBy('id');
    expect($rows[$standalone->id]['registry']['name'] ?? $rows[$standalone->id]['registry'])->toBe('Zeta Interna');

    $filtered = standaloneGridRows(['filterModel' => ['registry' => ['filterType' => 'set', 'values' => ['Zeta Interna']]]]);
    expect(collect($filtered)->pluck('id')->all())->toBe([$standalone->id]);

    $searched = standaloneGridRows(['search' => 'Zeta']);
    expect(collect($searched)->pluck('id')->all())->toBe([$standalone->id]);

    $sorted = standaloneGridRows(['sortModel' => [['colId' => 'registry', 'sort' => 'asc']]]);
    expect(collect($sorted)->pluck('id')->all())->toBe([$fromOffer->id, $standalone->id]);
});

it('offers the own client among the distinct values of the client filter (AC-006)', function () {
    Sanctum::actingAs(projectWorkOrderViewer());
    standaloneProjectWorkOrder(['registry_id' => Registry::factory()->create(['name' => 'Zeta Interna'])->id]);

    $values = $this->postJson('/api/tables/project-work-orders/values', ['columnId' => 'registry', 'filterModel' => new stdClass, 'limit' => 50])
        ->assertOk()->json('data.values');

    expect($values)->toContain('Zeta Interna');
});

it('is found by its client: the registry filter and the for-select (AC-006)', function () {
    Sanctum::actingAs(projectWorkOrderViewer());
    $standalone = standaloneProjectWorkOrder();
    standaloneOfferWorkOrder('Altro');

    $ids = WorkOrder::query()->withQuoteLineOfRegistry($standalone->registry_id)->pluck('id')->all();
    expect($ids)->toBe([$standalone->id]);

    $this->getJson("/api/work-orders/for-select?registry_id={$standalone->registry_id}")
        ->assertOk()
        ->assertJsonPath('items.0.id', $standalone->id)
        ->assertJsonPath('items.0.meta.registry_id', $standalone->registry_id)
        ->assertJsonCount(1, 'items');
});

it('is seen through its own operational site by the site tier (AC-007)', function () {
    $mine = OperationalSite::factory()->create();
    $inSite = standaloneProjectWorkOrder(['operational_site_id' => $mine->id]);
    standaloneProjectWorkOrder();
    $actor = projectWorkOrderViewer(viewAll: false);
    $actor->givePermissionTo(Permission::findOrCreate('work-orders.viewSite', 'web'));
    EmploymentProfile::factory()->physicalSite($mine)->create(['user_id' => $actor->id]);
    Sanctum::actingAs($actor);

    expect(collect(standaloneGridRows())->pluck('id')->all())->toBe([$inSite->id]);
    $this->getJson("/api/work-orders/{$inSite->id}")->assertOk();
});

it('is invisible to an actor with no tier reaching it (AC-007)', function () {
    $workOrder = standaloneProjectWorkOrder();
    Sanctum::actingAs(projectWorkOrderViewer(viewAll: false));

    expect(standaloneGridRows())->toBe([]);
    $this->getJson("/api/work-orders/{$workOrder->id}")->assertForbidden();
});

it('belongs to the categories of its own business-function rows (D-10/D-11)', function () {
    $workOrder = standaloneProjectWorkOrder();
    $categoryId = $workOrder->productLines->first()->product_category_id;

    $ids = WorkOrder::query()->whereIn('id', WorkOrderSourceQuotes::withRevenueCategory([$categoryId]))->pluck('id')->all();

    expect($ids)->toBe([$workOrder->id]);
});
