<?php

use App\Enums\PurchaseOrderStatus as Status;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderStatusLabel;
use App\Services\PurchaseOrderStatusLabelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// spec 0248, AC-005: the ODA grid takes the status badges and the status order from the catalogue.

function purchaseOrderStatusBadges(object $test): array
{
    $columns = $test->getJson('/api/tables/purchase-orders/columns')->assertOk()->json('data.columns');

    return collect($columns)->firstWhere('id', 'status')['badges'];
}

it('AC-005: the status column exposes the catalogue badges and follows a rename', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view']));

    $badges = purchaseOrderStatusBadges($this);

    expect(collect($badges)->pluck('value')->all())->toBe(Status::values())
        ->and($badges[0])->toMatchArray(['value' => 'draft', 'label' => 'Bozza', 'color' => 'slate', 'icon' => 'pen-line']);

    PurchaseOrderStatusLabel::query()->where('system_key', 'draft')->update(['name' => 'In bozza', 'color' => 'teal', 'icon' => null]);

    expect(purchaseOrderStatusBadges($this)[0])->toMatchArray(['value' => 'draft', 'label' => 'In bozza', 'color' => 'teal', 'icon' => null]);
});

it('AC-005: the badges are read with one catalogue query, not one per row', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view']));
    foreach ([Status::Draft, Status::Approved, Status::Cancelled] as $status) {
        PurchaseOrder::factory()->withStatus($status)->create();
    }

    DB::enableQueryLog();
    $this->postJson('/api/tables/purchase-orders/rows', ['startRow' => 0, 'endRow' => 50])->assertOk();
    $catalogueQueries = collect(DB::getQueryLog())->filter(fn (array $query) => str_contains($query['query'], 'purchase_order_statuses'));
    DB::disableQueryLog();

    expect($catalogueQueries->count())->toBeLessThanOrEqual(1);
});

it('AC-005: the status filter still works on the enum value and the sort follows the catalogue order', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view']));
    $draft = PurchaseOrder::factory()->withStatus(Status::Draft)->create();
    $approved = PurchaseOrder::factory()->withStatus(Status::Approved)->create();
    $cancelled = PurchaseOrder::factory()->withStatus(Status::Cancelled)->create();
    $sorted = fn (string $direction): array => collect($this->postJson('/api/tables/purchase-orders/rows', [
        'startRow' => 0, 'endRow' => 50, 'sortModel' => [['colId' => 'status', 'sort' => $direction]],
    ])->assertOk()->json('items'))->pluck('id')->all();

    $filtered = $this->postJson('/api/tables/purchase-orders/rows', [
        'startRow' => 0, 'endRow' => 50, 'filterModel' => ['status' => ['filterType' => 'set', 'values' => ['approved']]],
    ])->assertOk()->json('items');

    expect(collect($filtered)->pluck('id')->all())->toBe([$approved->id])
        ->and($sorted('asc'))->toBe([$draft->id, $approved->id, $cancelled->id]);

    $ids = PurchaseOrderStatusLabel::query()->orderBy('sort_order')->pluck('id')->reverse()->values()->all();
    app(PurchaseOrderStatusLabelService::class)->reorder($ids);

    expect($sorted('asc'))->toBe([$cancelled->id, $approved->id, $draft->id])
        ->and($sorted('desc'))->toBe([$draft->id, $approved->id, $cancelled->id]);
});
