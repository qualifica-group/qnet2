<?php

use App\Enums\MigrationStatus;
use App\Enums\PurchaseRequestLineStatus;
use App\Enums\PurchaseRequestStatus;
use App\Migrations\MigrationOrder;
use App\Migrations\MigrationRegistry;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\PurchaseRequestPriority;
use App\Models\Registry;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VatRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// Fixtures (legacyPurchaseRequestRecord, legacyPurchaseRequestAnchors, ...) live in
// tests/Helpers/PurchaseRequestMigrationHelpers.php; the report readers
// (legacyTaskWarnings/legacyTaskErrors) in TaskMigrationHelpers.php.

beforeEach(function () {
    seedMigrationsConfig();
    $this->anchors = legacyPurchaseRequestAnchors();
});

it('AC-001 imports a complete legacy RDA with its lines and anchors, without log or notification', function () {
    Notification::fake();
    $creator = User::factory()->create(['old_id' => 20]);
    $approver = User::factory()->create(['old_id' => 21]);
    $customer = Registry::factory()->create(['old_id' => 30]);
    $supplier = Registry::factory()->create(['old_id' => 31]);
    $vatRate = VatRate::factory()->create(['old_id' => 40, 'rate' => 22]);
    $unit = UnitOfMeasure::factory()->create(['code' => 'package']);
    $actor = migrationsSuperAdminActor();
    $activityBefore = DB::table('activity_log')->count();

    $run = runLegacyPurchaseRequestsImport([legacyPurchaseRequestRecord([
        'creator_user_id' => 20,
        'customer_id' => 30,
        'notes' => 'General notes',
        'delivery_terms' => 'Within May',
        'lines' => [
            legacyPurchaseRequestLine([
                'supplier_id' => 31,
                'unit_of_measure_code' => 'package',
                'quantity' => 3,
                'unit_price' => 10.1,
                'vat_rate_id' => 40,
                'reason' => 'Needed for the course',
                'status' => 'approved',
                'approved_by_user_id' => 21,
                'approved_at' => '2025-03-03 09:00:00',
            ]),
            legacyPurchaseRequestLine(['id' => 901, 'position' => 2, 'quantity' => 1, 'unit_price' => 5]),
        ],
    ])], $actor);

    $request = PurchaseRequest::query()->where('old_id', 600)->sole();
    $line = PurchaseRequestLine::query()->where('old_id', 900)->sole();

    expect($run->status)->toBe(MigrationStatus::Completed)
        ->and($run->created_rows)->toBe(1)
        ->and($run->report)->toBeNull()
        ->and($request->subject)->toBe('Legacy RDA')
        ->and($request->requested_at->toDateString())->toBe('2025-03-01')
        ->and($request->purchase_request_priority_id)->toBe(PurchaseRequestPriority::query()->where('name', 'Alta')->value('id'))
        ->and($request->requester_id)->toBe($this->anchors['requester']->id)
        ->and($request->function_manager_id)->toBe($this->anchors['manager']->id)
        ->and($request->created_by)->toBe($creator->id)
        ->and($request->customer_id)->toBe($customer->id)
        ->and($request->work_order_id)->toBe($this->anchors['workOrder']->id)
        ->and($request->company_id)->toBe($this->anchors['company']->id)
        ->and($request->company_site_id)->toBe($this->anchors['companySite']->id)
        ->and($request->operational_site_id)->toBe($this->anchors['operationalSite']->id)
        ->and($request->business_function_id)->toBe($this->anchors['businessFunction']->id)
        ->and($request->notes)->toBe('General notes')
        ->and($request->delivery_terms)->toBe('Within May')
        ->and($request->status)->toBe(PurchaseRequestStatus::Open)
        ->and($request->created_at->toDateTimeString())->toBe('2025-03-01 08:00:00')
        ->and($request->updated_at->toDateTimeString())->toBe('2025-03-05 08:00:00')
        ->and($request->taxable_total)->toBe('35.30')
        ->and($request->vat_total)->toBe('6.67')
        ->and($request->grand_total)->toBe('41.97')
        ->and($line->purchase_request_id)->toBe($request->id)
        ->and($line->position)->toBe(1)
        ->and($line->supplier_id)->toBe($supplier->id)
        ->and($line->unit_of_measure_id)->toBe($unit->id)
        ->and($line->quantity)->toBe('3.000')
        ->and($line->unit_price)->toBe('10.10')
        ->and($line->vat_rate_id)->toBe($vatRate->id)
        ->and($line->taxable_amount)->toBe('30.30')
        ->and($line->vat_amount)->toBe('6.67')
        ->and($line->reason)->toBe('Needed for the course')
        ->and($line->status)->toBe(PurchaseRequestLineStatus::Approved)
        ->and($line->approved_by)->toBe($approver->id)
        ->and($line->approved_at->toDateTimeString())->toBe('2025-03-03 09:00:00')
        ->and($line->created_at->toDateTimeString())->toBe('2025-03-01 08:00:00')
        ->and(DB::table('activity_log')->count())->toBe($activityBefore);

    Notification::assertNothingSent();
});

it('AC-002 skips an RDA already imported', function () {
    runLegacyPurchaseRequestsImport([legacyPurchaseRequestRecord()]);

    $run = runLegacyPurchaseRequestsImport([legacyPurchaseRequestRecord(['subject' => 'Changed'])]);

    expect($run->skipped_rows)->toBe(1)
        ->and(PurchaseRequest::query()->sole()->subject)->toBe('Legacy RDA')
        ->and(PurchaseRequestLine::query()->count())->toBe(1);
});

it('AC-003 fails the row when a required anchor is missing or not migrated', function (array $overrides, string $reason) {
    $run = runLegacyPurchaseRequestsImport([legacyPurchaseRequestRecord($overrides)]);

    expect($run->failed_rows)->toBe(1)
        ->and(PurchaseRequest::query()->count())->toBe(0)
        ->and(PurchaseRequestLine::query()->count())->toBe(0)
        ->and(legacyTaskErrors($run)[0])->toContain($reason);
})->with([
    'requester' => [['requester_user_id' => 99], 'Requester (legacy id 99) not migrated'],
    'function manager' => [['function_manager_user_id' => 99], 'Function manager (legacy id 99) not migrated'],
    'creator' => [['creator_user_id' => 99], 'Creator (legacy id 99) not migrated'],
    'company' => [['company_id' => null], 'Company is required'],
    'company site' => [['company_site_id' => 99], 'Company site (legacy id 99) not migrated'],
    'operational site' => [['operational_site_id' => 99], 'Operational site (legacy id 99) not migrated'],
    'business function' => [['business_function_id' => 99], 'Business function (legacy id 99) not migrated'],
    'work order' => [['work_order_id' => 99], 'Work order (legacy id 99) not migrated'],
    'line status' => [['lines' => [legacyPurchaseRequestLine(['status' => 'lost'])]], "status 'lost' is not valid"],
]);

it('AC-003 fails the row when the company site belongs to another company', function () {
    $otherSite = CompanySite::factory()->create(['company_id' => Company::factory()->create()->id]);
    $otherSite->forceFill(['old_id' => 50])->save();

    $run = runLegacyPurchaseRequestsImport([legacyPurchaseRequestRecord(['company_site_id' => 50])]);

    expect($run->failed_rows)->toBe(1)
        ->and(legacyTaskErrors($run)[0])->toContain('does not belong to the company');
});

it('AC-004 leaves optional references empty with a warning', function () {
    $run = runLegacyPurchaseRequestsImport([legacyPurchaseRequestRecord([
        'customer_id' => 98,
        'priority_name' => 'Molto alta',
        'lines' => [legacyPurchaseRequestLine([
            'supplier_id' => 97,
            'vat_rate_id' => 96,
            'product_id' => 95,
            'unit_of_measure_code' => 'cubic_metre',
            'approved_by_user_id' => 94,
        ])],
    ])]);

    $request = PurchaseRequest::query()->sole();
    $line = $request->lines()->sole();

    expect($run->created_rows)->toBe(1)
        ->and($request->customer_id)->toBeNull()
        ->and($request->purchase_request_priority_id)->toBe(PurchaseRequestPriority::query()->where('is_default', true)->value('id'))
        ->and($line->supplier_id)->toBeNull()
        ->and($line->vat_rate_id)->toBeNull()
        ->and($line->vat_amount)->toBe('0.00')
        ->and($line->product_id)->toBeNull()
        ->and($line->unit_of_measure_id)->toBeNull()
        ->and($line->approved_by)->toBeNull()
        ->and(legacyTaskWarnings($run))->toHaveCount(7);
});

it('AC-005 imports the status history and skips the unusable entries with a warning', function () {
    $author = User::factory()->create(['old_id' => 22]);

    $run = runLegacyPurchaseRequestsImport([legacyPurchaseRequestRecord([
        'lines' => [legacyPurchaseRequestLine([
            'status' => 'approved',
            'status_logs' => [
                ['user_id' => 22, 'from_status' => 'pending_approval', 'to_status' => 'approved', 'reason' => 'Ok',
                    'bulk_group_id' => '87fa9a92-8220-4188-a06b-dba10c3876ff', 'created_at' => '2025-03-03 10:00:00'],
                ['user_id' => 93, 'from_status' => 'pending_approval', 'to_status' => 'approved', 'reason' => null,
                    'bulk_group_id' => null, 'created_at' => '2025-03-03 11:00:00'],
            ],
        ])],
    ])]);

    $log = PurchaseRequestLine::query()->sole()->statusLogs()->sole();

    expect($log->user_id)->toBe($author->id)
        ->and($log->from_status)->toBe(PurchaseRequestLineStatus::PendingApproval)
        ->and($log->to_status)->toBe(PurchaseRequestLineStatus::Approved)
        ->and($log->reason)->toBe('Ok')
        ->and($log->bulk_group_id)->toBe('87fa9a92-8220-4188-a06b-dba10c3876ff')
        ->and($log->created_at->toDateTimeString())->toBe('2025-03-03 10:00:00')
        ->and(legacyTaskWarnings($run))->toBe(['Line 900: status change skipped (user not migrated or status not valid).']);
});

it('AC-006 keeps a legacy closed RDA closed even with lines still open', function () {
    $closer = User::factory()->create(['old_id' => 23]);

    runLegacyPurchaseRequestsImport([legacyPurchaseRequestRecord([
        'is_closed' => true,
        'closed_by_user_id' => 23,
        'closed_at' => '2025-04-01 12:00:00',
        'close_reason' => 'Not needed any more',
    ])]);

    $request = PurchaseRequest::query()->sole();

    expect($request->status)->toBe(PurchaseRequestStatus::Closed)
        ->and($request->closed_by)->toBe($closer->id)
        ->and($request->closed_at->toDateTimeString())->toBe('2025-04-01 12:00:00')
        ->and($request->close_reason)->toBe('Not needed any more')
        ->and($request->lines()->sole()->status)->toBe(PurchaseRequestLineStatus::PendingApproval);
});

it('AC-007 closes an open RDA whose lines are all terminal with the last status change', function () {
    $author = User::factory()->create(['old_id' => 24]);

    $run = runLegacyPurchaseRequestsImport([legacyPurchaseRequestRecord([
        'lines' => [
            legacyPurchaseRequestLine(['status' => 'received', 'status_logs' => [
                ['user_id' => 24, 'from_status' => 'ordered', 'to_status' => 'received', 'reason' => null,
                    'bulk_group_id' => null, 'created_at' => '2025-03-20 09:00:00'],
            ]]),
            legacyPurchaseRequestLine(['id' => 901, 'position' => 2, 'status' => 'rejected']),
        ],
    ])]);

    $request = PurchaseRequest::query()->sole();

    expect($request->status)->toBe(PurchaseRequestStatus::Closed)
        ->and($request->closed_by)->toBe($author->id)
        ->and($request->closed_at->toDateTimeString())->toBe('2025-03-20 09:00:00')
        ->and($request->close_reason)->toBeNull()
        ->and(legacyTaskWarnings($run))->toBe(['Closed at import: every line is in a terminal state.']);
});

it('AC-007 closes without history on the legacy update date, and leaves open an RDA with an open line', function () {
    runLegacyPurchaseRequestsImport([
        legacyPurchaseRequestRecord(['lines' => [legacyPurchaseRequestLine(['status' => 'on_hold'])]]),
        legacyPurchaseRequestRecord(['id' => 601, 'lines' => [
            legacyPurchaseRequestLine(['id' => 902, 'status' => 'received']),
            legacyPurchaseRequestLine(['id' => 903, 'status' => 'ordered']),
        ]]),
    ]);

    $closed = PurchaseRequest::query()->where('old_id', 600)->sole();
    $open = PurchaseRequest::query()->where('old_id', 601)->sole();

    expect($closed->status)->toBe(PurchaseRequestStatus::Closed)
        ->and($closed->closed_by)->toBeNull()
        ->and($closed->closed_at->toDateTimeString())->toBe('2025-03-05 08:00:00')
        ->and($open->status)->toBe(PurchaseRequestStatus::Open)
        ->and($open->closed_at)->toBeNull();
});

it('AC-008 truncates long texts and copies the legacy warnings', function () {
    $run = runLegacyPurchaseRequestsImport([legacyPurchaseRequestRecord([
        'subject' => str_repeat('s', 300),
        'warnings' => ['Fornitore 21110 inesistente: collegamento omesso.'],
        'lines' => [legacyPurchaseRequestLine(['description' => str_repeat('d', 600)])],
    ])]);

    $request = PurchaseRequest::query()->sole();

    expect(mb_strlen($request->subject))->toBe(255)
        ->and(mb_strlen($request->lines()->sole()->description))->toBe(500)
        ->and(legacyTaskWarnings($run))->toBe([
            'Fornitore 21110 inesistente: collegamento omesso.',
            'Subject truncated to 255 characters.',
            'Line 900: description truncated to 500 characters.',
        ]);
});

it('AC-009 registers the source in the phase after tasks with its dependencies', function () {
    $source = app(MigrationRegistry::class)->resolve('purchase-requests');

    expect(MigrationOrder::phaseOf('purchase-requests'))->toBe(MigrationOrder::phaseOf('tasks') + 1)
        ->and(array_map(fn ($dependency) => [$dependency->source, $dependency->required], $source->dependencies()))->toBe([
            ['users', true], ['companies', true], ['company-sites', true], ['operational-sites', true],
            ['business-functions', true], ['work-orders', true], ['registries', false], ['vat-rates', false],
        ]);
});
