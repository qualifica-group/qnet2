<?php

use App\Enums\PurchaseRequestLineStatus as LineStatus;
use App\Models\ExportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function requestRows(object $test, array $state = [], string $domain = 'purchase-requests'): array
{
    return $test->postJson("/api/tables/{$domain}/rows", ['startRow' => 0, 'endRow' => 50] + $state)->assertOk()->json('items');
}

// ---------------------------------------------------------------------------
// AC-003 — visibility on grids, detail and export
// ---------------------------------------------------------------------------

it('AC-003: without viewAll the grids and the detail show only the requests the user is involved in', function () {
    $user = purchaseRequestUserWith(['view']);
    $asRequester = purchaseRequestWithLines([LineStatus::Approved], ['requester_id' => $user->id, 'created_by' => User::factory()->create()->id]);
    $asManager = purchaseRequestWithLines([LineStatus::Approved], ['function_manager_id' => $user->id]);
    $asAuthor = purchaseRequestWithLines([LineStatus::Approved], ['created_by' => $user->id]);
    $foreign = purchaseRequestWithLines([LineStatus::Approved]);
    Sanctum::actingAs($user);

    $visible = collect([$asRequester, $asManager, $asAuthor])->pluck('id')->all();

    expect(collect(requestRows($this))->pluck('id')->all())->toEqualCanonicalizing($visible)
        ->and(collect(requestRows($this, [], 'purchase-request-lines'))->pluck('purchase_request_id')->unique()->all())->toEqualCanonicalizing($visible);

    $this->getJson("/api/purchase-requests/{$asRequester->id}")->assertOk();
    $this->getJson("/api/purchase-requests/{$foreign->id}")->assertForbidden();
    $this->getJson("/api/purchase-request-lines/{$foreign->lines->first()->id}/status-logs")->assertForbidden();

    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll']));
    expect(requestRows($this))->toHaveCount(4);
});

it('AC-003: the grids need the view permission', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['create']));

    $this->postJson('/api/tables/purchase-requests/rows', ['startRow' => 0, 'endRow' => 10])->assertForbidden();
    $this->postJson('/api/tables/purchase-request-lines/rows', ['startRow' => 0, 'endRow' => 10])->assertForbidden();
});

it('AC-003: the export contains only the visible requests', function () {
    Storage::fake('local');
    $user = purchaseRequestUserWith(['view', 'export']);
    purchaseRequestWithLines([LineStatus::Approved], ['requester_id' => $user->id, 'subject' => 'Mine visible']);
    purchaseRequestWithLines([LineStatus::Approved], ['subject' => 'Foreign hidden']);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/exports/purchase-requests', [
        'format' => 'csv',
        'columns' => [['colId' => 'subject', 'header' => 'Subject']],
    ])->assertCreated();

    $csv = Storage::disk('local')->get(ExportRun::findOrFail($response->json('data.export_run.id'))->file_path);

    expect($csv)->toContain('Mine visible')->not->toContain('Foreign hidden');
});

it('AC-003: the export needs the export permission', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view']));

    $this->postJson('/api/exports/purchase-requests', ['format' => 'csv', 'columns' => [['colId' => 'subject', 'header' => 'Subject']]])->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-014 — allow-listed filters, sort, status tabs
// ---------------------------------------------------------------------------

it('AC-014: the RDA grid carries the contract columns and the status tab keeps requests with a line in that status', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'update', 'delete', 'close']));
    $withApproved = purchaseRequestWithLines([LineStatus::Approved, LineStatus::Received], ['subject' => 'Has approved']);
    purchaseRequestWithLines([LineStatus::Rejected], ['subject' => 'Only rejected']);

    $rows = requestRows($this, ['filterModel' => ['line_status' => ['filterType' => 'set', 'values' => ['approved']]]]);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['id'])->toBe($withApproved->id)
        ->and(array_keys($rows[0]))->toContain(
            'subject', 'requested_at', 'priority', 'requester', 'function_manager', 'customer', 'supplier', 'work_order',
            'company', 'company_site', 'operational_site', 'business_function', 'created_by', 'taxable_total',
            'vat_total', 'grand_total', 'status', 'line_status_counts', 'abilities', 'actions',
        )
        ->and($rows[0]['line_status_counts']['approved'])->toBe(1)
        ->and($rows[0]['line_status_counts']['received'])->toBe(1)
        ->and($rows[0]['actions'])->toEqualCanonicalizing(['view', 'update', 'notify_manager', 'close', 'delete']);
});

it('AC-014: filters, search and sort run from the allow-list; unknown columns are ignored or refused, never interpolated', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll']));
    $alpha = purchaseRequestWithLines([LineStatus::Approved], ['subject' => 'Alpha order', 'priority' => 'low']);
    $beta = purchaseRequestWithLines([LineStatus::Approved], ['subject' => 'Beta order', 'priority' => 'urgent']);

    $subject = fn (array $state) => collect(requestRows($this, $state))->pluck('subject')->all();

    expect($subject(['filterModel' => ['subject' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'Alpha']]]))->toBe(['Alpha order'])
        ->and($subject(['filterModel' => ['priority' => ['filterType' => 'set', 'values' => ['urgent']]]]))->toBe(['Beta order'])
        ->and($subject(['filterModel' => ['requester' => ['filterType' => 'text', 'filter' => $beta->requester->name]]]))->toBe(['Beta order'])
        ->and($subject(['search' => 'Alpha']))->toBe(['Alpha order'])
        ->and($subject(['sortModel' => [['colId' => 'subject', 'sort' => 'desc']]]))->toBe(['Beta order', 'Alpha order'])
        ->and($subject(['sortModel' => [['colId' => 'requester', 'sort' => 'asc']]]))->toHaveCount(2)
        ->and($subject(['filterModel' => ['subject' => ['filterType' => 'text', 'filter' => "'; drop table users; --"]]]))->toBe([]);

    // A column outside the catalogue is refused by the engine, never run.
    $this->postJson('/api/tables/purchase-requests/rows', [
        'startRow' => 0, 'endRow' => 10, 'filterModel' => ['id; drop table users' => ['filterType' => 'text', 'filter' => 'x']],
    ])->assertUnprocessable();
    $this->postJson('/api/tables/purchase-requests/rows', [
        'startRow' => 0, 'endRow' => 10, 'sortModel' => [['colId' => 'subject; select 1', 'sort' => 'asc']],
    ])->assertUnprocessable();

    expect($alpha->fresh())->not->toBeNull();
});

it('AC-014: the quick search also matches the supplier VAT number', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll']));
    $supplier = supplierRegistry();
    $supplier->personalData()->create(['type' => 'company', 'company_name' => 'Forniture Spa', 'vat_number' => '01234567890']);
    $withSupplier = purchaseRequestWithLines([LineStatus::Approved], ['supplier_id' => $supplier->id]);
    purchaseRequestWithLines();

    expect(collect(requestRows($this, ['search' => '0123456']))->pluck('id')->all())->toBe([$withSupplier->id]);
});

it('AC-014: the lines grid exposes the transitions of each row and filters by status', function () {
    $manager = purchaseRequestUserWith(['view']);
    $request = purchaseRequestWithLines([LineStatus::PendingApproval, LineStatus::Approved], ['function_manager_id' => $manager->id]);
    Sanctum::actingAs($manager);

    $rows = collect(requestRows($this, [], 'purchase-request-lines'))->keyBy('id');

    expect($rows)->toHaveCount(2)
        ->and($rows[$request->lines[0]->id]['abilities']['transitions'])->toBe(['approved', 'rejected'])
        ->and($rows[$request->lines[1]->id]['abilities']['transitions'])->toBe([])
        ->and(array_keys($rows->first()))->toContain(
            'purchase_request_id', 'purchase_request_subject', 'priority', 'requester', 'function_manager', 'requested_at',
            'description', 'unit_of_measure', 'quantity', 'unit_price', 'taxable_amount', 'vat_amount', 'total_amount',
            'status', 'approved_by', 'approved_at', 'oda_reference', 'abilities',
        );

    $tab = requestRows($this, ['filterModel' => ['status' => ['filterType' => 'set', 'values' => ['approved']]]], 'purchase-request-lines');
    expect($tab)->toHaveCount(1)->and($tab[0]['id'])->toBe($request->lines[1]->id);

    $byParent = requestRows($this, [
        'filterModel' => ['purchase_request_subject' => ['filterType' => 'text', 'filter' => 'zzz-nothing']],
        'sortModel' => [['colId' => 'requested_at', 'sort' => 'desc']],
    ], 'purchase-request-lines');
    expect($byParent)->toBe([]);
});

it('AC-014: every filterable column of both grids answers the rows and the distinct-values endpoints', function (string $domain) {
    $manager = purchaseRequestUserWith(['view', 'viewAll']);
    purchaseRequestWithLines([LineStatus::PendingApproval, LineStatus::Approved], ['function_manager_id' => $manager->id]);
    Sanctum::actingAs($manager);

    $filters = $this->getJson("/api/tables/{$domain}/columns")->assertOk()->json('data.filters') ?? $this->getJson("/api/tables/{$domain}/columns")->json('filters');

    foreach ($filters as $filter) {
        $model = match ($filter['type']) {
            'set' => ['filterType' => 'set', 'values' => [$filter['options'][0] ?? 'x']],
            'number' => ['filterType' => 'number', 'type' => 'greaterThan', 'filter' => 0],
            'date' => ['filterType' => 'date', 'type' => 'greaterThan', 'dateFrom' => '2000-01-01'],
            default => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'a'],
        };

        $this->postJson("/api/tables/{$domain}/rows", ['startRow' => 0, 'endRow' => 10, 'filterModel' => [$filter['columnId'] => $model]])
            ->assertOk();
        $this->postJson("/api/tables/{$domain}/values", ['columnId' => $filter['columnId']])
            ->assertOk();
    }
})->with(['purchase-requests', 'purchase-request-lines']);

it('AC-014: the value list of a related column offers its names and filters by the picked ones', function (string $domain) {
    $viewer = purchaseRequestUserWith(['view', 'viewAll']);
    $alpha = User::factory()->create(['name' => 'Alpha Requester']);
    $beta = User::factory()->create(['name' => 'Beta Requester']);
    $first = purchaseRequestWithLines([LineStatus::PendingApproval], ['requester_id' => $alpha->id]);
    purchaseRequestWithLines([LineStatus::PendingApproval], ['requester_id' => $beta->id]);
    Sanctum::actingAs($viewer);

    $values = $this->postJson("/api/tables/{$domain}/values", ['columnId' => 'requester'])->assertOk()->json();
    expect(json_encode($values))->toContain('Alpha Requester')->toContain('Beta Requester');

    $rows = requestRows($this, ['filterModel' => ['requester' => ['filterType' => 'set', 'values' => ['Alpha Requester']]]], $domain);
    $ids = collect($rows)->map(fn (array $row): int => $row['purchase_request_id'] ?? $row['id'])->unique()->values()->all();
    expect($ids)->toBe([$first->id]);
})->with(['purchase-requests', 'purchase-request-lines']);
