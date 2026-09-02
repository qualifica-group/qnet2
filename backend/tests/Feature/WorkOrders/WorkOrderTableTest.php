<?php

use App\Enums\WorkOrderType;
use App\Models\ExportRun;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('workOrderUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function workOrderUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("work-orders.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("work-orders.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// columns config — AC-040
// ---------------------------------------------------------------------------

it('GET /api/tables/work-orders/columns declares the 10 columns, status non-sortable + set filter (AC-040)', function () {
    $actor = workOrderUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/work-orders/columns')->assertOk()->json('data');

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe(['id', 'code', 'title', 'contract_number', 'quote', 'type', 'callback_date', 'is_force_closed', 'status', 'created_at', 'updated_at']);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['status']['sortable'])->toBeFalse()
        ->and($columns['status']['filterType'])->toBe('set')
        ->and($columns['status']['type'])->toBe('badge')
        ->and($columns['type']['filterType'])->toBe('set')
        ->and($columns['is_force_closed']['filterType'])->toBe('set')
        ->and($columns['contract_number']['filterType'])->toBe('text')
        ->and($columns['quote']['filterType'])->toBe('text')
        ->and($data['searchable'])->toEqualCanonicalizing(['code', 'title', 'contract_number']);
});

it('the `type`/`status` badge columns declare their enumKey + full badges catalogue (AC-040)', function () {
    $actor = workOrderUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $columns = collect($this->getJson('/api/tables/work-orders/columns')->assertOk()->json('data.columns'))->keyBy('id');

    expect($columns['type']['enumKey'])->toBe('work_order_type')
        ->and(collect($columns['type']['badges'])->pluck('value')->all())->toEqualCanonicalizing(['processing', 'project'])
        ->and($columns['status']['enumKey'])->toBe('work_order_status')
        ->and(collect($columns['status']['badges'])->pluck('value')->all())->toEqualCanonicalizing(['open', 'closed'])
        ->and($columns['is_force_closed'])->not->toHaveKey('enumKey');
});

it('distinct-values for `type`/`status` returns the FULL declared set, not just values present in current rows (AC-040)', function () {
    $actor = workOrderUserWith(['viewAny']);
    WorkOrder::factory()->create(['type' => WorkOrderType::Processing, 'is_force_closed' => false]);
    Sanctum::actingAs($actor);

    $typeValues = $this->postJson('/api/tables/work-orders/values', ['columnId' => 'type', 'limit' => 25])
        ->assertOk()->json('data.values');
    expect($typeValues)->toEqualCanonicalizing(['processing', 'project']);

    $statusValues = $this->postJson('/api/tables/work-orders/values', ['columnId' => 'status', 'limit' => 25])
        ->assertOk()->json('data.values');
    expect($statusValues)->toEqualCanonicalizing(['open', 'closed']);
});

it('403 without work-orders.viewAny', function () {
    $actor = workOrderUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/tables/work-orders/columns')->assertForbidden();
});

// ---------------------------------------------------------------------------
// contract_number/quote derived from quotes.code/quotes.title — AC-041
// ---------------------------------------------------------------------------

it('sorts by contract_number using quotes.code (AC-041)', function () {
    $actor = workOrderUserWith(['viewAny', 'view']);
    $quoteA = Quote::factory()->create(['code' => 'QUO-0001']);
    $quoteB = Quote::factory()->create(['code' => 'QUO-0002']);
    WorkOrder::factory()->create(['quote_id' => $quoteB->id, 'title' => 'B']);
    WorkOrder::factory()->create(['quote_id' => $quoteA->id, 'title' => 'A']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/work-orders/rows', [
        'startRow' => 0, 'endRow' => 25,
        'sortModel' => [['colId' => 'contract_number', 'sort' => 'asc']],
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('contract_number')->all())->toBe(['QUO-0001', 'QUO-0002']);
});

it('filters by contract_number text on quotes.code (AC-041)', function () {
    $actor = workOrderUserWith(['viewAny', 'view']);
    $matching = Quote::factory()->create(['code' => 'QUO-1234']);
    $other = Quote::factory()->create(['code' => 'QUO-9999']);
    WorkOrder::factory()->create(['quote_id' => $matching->id, 'title' => 'Matching']);
    WorkOrder::factory()->create(['quote_id' => $other->id, 'title' => 'Other']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/work-orders/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['contract_number' => ['filterType' => 'text', 'type' => 'contains', 'filter' => '1234']],
    ])->assertOk();

    $titles = collect($response->json('items'))->pluck('title')->all();
    expect($titles)->toBe(['Matching']);
});

it('rows expose `quote` as quotes.title (AC-041)', function () {
    $actor = workOrderUserWith(['viewAny', 'view']);
    $quote = Quote::factory()->create(['title' => 'Offerta di riferimento']);
    WorkOrder::factory()->create(['quote_id' => $quote->id, 'title' => 'Row']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('title', 'Row');

    expect($row['quote'])->toBe('Offerta di riferimento');
});

// ---------------------------------------------------------------------------
// status filter matches the badge exactly — AC-034
// ---------------------------------------------------------------------------

it('filtering status=closed returns exactly the rows the Resource badges closed (AC-034)', function () {
    $actor = workOrderUserWith(['viewAny', 'view']);
    $open = WorkOrder::factory()->create(['is_force_closed' => false, 'title' => 'Open one']);
    $closed = WorkOrder::factory()->forceClosed()->create(['title' => 'Closed one']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/work-orders/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['status' => ['filterType' => 'set', 'values' => ['closed']]],
    ])->assertOk();

    $titles = collect($response->json('items'))->pluck('title')->all();
    expect($titles)->toBe(['Closed one']);

    $detail = $this->getJson("/api/work-orders/{$closed->id}")->json('data');
    expect($detail['status']['value'])->toBe('closed');

    $openDetail = $this->getJson("/api/work-orders/{$open->id}")->json('data');
    expect($openDetail['status']['value'])->toBe('open');
});

// ---------------------------------------------------------------------------
// export — AC-042
// ---------------------------------------------------------------------------

it('POST /api/exports/work-orders exports the visible columns (AC-042)', function () {
    Storage::fake('local');
    $actor = workOrderUserWith(['viewAny', 'view', 'export']);
    $quote = Quote::factory()->create(['code' => 'QUO-5001']);
    WorkOrder::factory()->create(['quote_id' => $quote->id, 'code' => 'COM-5001', 'title' => 'Esportata']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/exports/work-orders', [
        'format' => 'csv',
        'columns' => [
            ['colId' => 'code', 'header' => 'Code'],
            ['colId' => 'title', 'header' => 'Title'],
        ],
    ])->assertCreated();

    $run = ExportRun::findOrFail($response->json('data.export_run.id'))->fresh();

    expect($run->row_count)->toBe(1);

    $csv = Storage::disk('local')->get($run->file_path);
    $lines = array_values(array_filter(explode("\n", trim($csv, "\xEF\xBB\xBF\n"))));
    $rows = array_map(static fn (string $line): array => str_getcsv($line, ',', '"', ''), $lines);

    expect($rows[0])->toBe(['Code', 'Title'])
        ->and($rows[1])->toBe(['COM-5001', 'Esportata']);
});

// ---------------------------------------------------------------------------
// bulk-delete — AC-043
// ---------------------------------------------------------------------------

it('bulk-delete goes through the same delete path: pivot rows cascade too (AC-043)', function () {
    $actor = workOrderUserWith(['viewAny', 'delete']);
    $workOrder = WorkOrder::factory()->create();
    $line = QuoteLine::factory()->create(['quote_id' => $workOrder->quote_id]);
    $workOrder->quoteLines()->attach($line->id);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/work-orders/bulk-delete', ['ids' => [$workOrder->id]])->assertOk();

    $this->assertDatabaseMissing('work_orders', ['id' => $workOrder->id]);
    $this->assertDatabaseMissing('quote_line_work_order', ['work_order_id' => $workOrder->id]);
});
