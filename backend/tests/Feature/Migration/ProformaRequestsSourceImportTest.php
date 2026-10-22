<?php

use App\Enums\ProformaRequestKind;
use App\Enums\ProformaRequestStatus;
use App\Migrations\MigrationOrder;
use App\Migrations\MigrationRegistry;
use App\Models\MigrationRun;
use App\Models\PaymentMethod;
use App\Models\ProformaRequest;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedMigrationsConfig();
    $this->paymentMethod = PaymentMethod::factory()->create();
    $this->quote = Quote::factory()->create(['payment_method_id' => $this->paymentMethod->id]);
    $this->quote->forceFill(['old_id' => 104988])->save();
    $this->workOrder = WorkOrder::factory()->create(['quote_id' => $this->quote->id]);
    $this->workOrder->forceFill(['old_id' => 20019])->save();
    $this->assignee = User::factory()->create(['old_id' => 127]);
    $this->requester = User::factory()->create(['old_id' => 116]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function legacyProformaRequestRecord(array $overrides = []): array
{
    return array_merge([
        'id' => 10907,
        'work_order_id' => 20019,
        'quote_id' => 104988,
        'kind' => 'consultancy',
        'supplier_id' => null,
        'status' => 'issued',
        'issued_at' => '2026-10-21 19:41:47',
        'note' => "Richiesta emissione Proforma: Commessa #20019\nBonifico",
        'assigned_to_user_id' => 127,
        'assigned_by_user_id' => 116,
        'warnings' => [],
        'created_at' => '2026-10-21 19:22:41',
        'updated_at' => '2026-10-21 19:41:47',
    ], $overrides);
}

/**
 * @param  array<int, array<string, mixed>>  $records
 */
function runLegacyProformaRequestsImport(array $records, ?User $actor = null): MigrationRun
{
    Http::fake([
        fakeMigrationsBaseUrl().'/proforma-requests*' => Http::response([
            'items' => $records,
            'pagination' => ['total' => count($records)],
        ]),
    ]);

    $run = MigrationRun::factory()->create(['user_id' => ($actor ?? migrationsSuperAdminActor())->id, 'source' => 'proforma-requests']);
    runMigrationJobFor($run);

    return $run->fresh();
}

it('AC-001 imports a request with its anchors, the quote payment method and the legacy state, without log', function () {
    $actor = migrationsSuperAdminActor();
    $activityBefore = DB::table('activity_log')->count();

    $run = runLegacyProformaRequestsImport([legacyProformaRequestRecord()], $actor);

    $proformaRequest = ProformaRequest::query()->where('old_id', 10907)->sole();

    expect($run->created_rows)->toBe(1)
        ->and($run->report)->toBeNull()
        ->and($proformaRequest->work_order_id)->toBe($this->workOrder->id)
        ->and($proformaRequest->quote_id)->toBe($this->quote->id)
        ->and($proformaRequest->kind)->toBe(ProformaRequestKind::Consultancy)
        ->and($proformaRequest->payment_method_id)->toBe($this->paymentMethod->id)
        ->and($proformaRequest->status)->toBe(ProformaRequestStatus::Issued)
        ->and($proformaRequest->issued_at->toDateTimeString())->toBe('2026-10-21 19:41:47')
        ->and($proformaRequest->note)->toBe("Richiesta emissione Proforma: Commessa #20019\nBonifico")
        ->and($proformaRequest->assigned_to)->toBe($this->assignee->id)
        ->and($proformaRequest->assigned_by)->toBe($this->requester->id)
        ->and($proformaRequest->created_at->toDateTimeString())->toBe('2026-10-21 19:22:41')
        ->and(DB::table('activity_log')->count())->toBe($activityBefore);
});

it('AC-001 imports a pending institution request with its supplier', function () {
    $supplier = Registry::factory()->create(['old_id' => 15269]);

    runLegacyProformaRequestsImport([legacyProformaRequestRecord(['kind' => 'institution', 'supplier_id' => 15269, 'status' => 'pending', 'issued_at' => null])]);

    $proformaRequest = ProformaRequest::query()->sole();

    expect($proformaRequest->kind)->toBe(ProformaRequestKind::Institution)
        ->and($proformaRequest->supplier_id)->toBe($supplier->id)
        ->and($proformaRequest->status)->toBe(ProformaRequestStatus::Pending)
        ->and($proformaRequest->issued_at)->toBeNull();
});

it('AC-002 skips a request already imported', function () {
    runLegacyProformaRequestsImport([legacyProformaRequestRecord()]);

    $run = runLegacyProformaRequestsImport([legacyProformaRequestRecord()]);

    expect($run->skipped_rows)->toBe(1)
        ->and(ProformaRequest::query()->count())->toBe(1);
});

it('AC-003 fails the row when a required anchor is missing or not migrated', function (array $overrides, string $reason) {
    $run = runLegacyProformaRequestsImport([legacyProformaRequestRecord($overrides)]);

    expect($run->failed_rows)->toBe(1)
        ->and(ProformaRequest::query()->count())->toBe(0)
        ->and(legacyTaskErrors($run)[0])->toContain($reason);
})->with([
    'work order' => [['work_order_id' => null], 'Work order is required'],
    'quote' => [['quote_id' => 99], 'Quote (legacy id 99) not migrated'],
    'assignee' => [['assigned_to_user_id' => 99], 'Assignee (legacy id 99) not migrated'],
    'requester' => [['assigned_by_user_id' => 99], 'Requester (legacy id 99) not migrated'],
]);

it('AC-004 leaves a supplier not migrated empty with a warning', function () {
    $run = runLegacyProformaRequestsImport([legacyProformaRequestRecord(['kind' => 'institution', 'supplier_id' => 98])]);

    expect(ProformaRequest::query()->sole()->supplier_id)->toBeNull()
        ->and(legacyTaskWarnings($run))->toHaveCount(1);
});

it('AC-005 registers the source in the phase of tasks with its dependencies', function () {
    $source = app(MigrationRegistry::class)->resolve('proforma-requests');

    expect(MigrationOrder::phaseOf('proforma-requests'))->toBe(MigrationOrder::phaseOf('tasks'))
        ->and(array_map(fn ($dependency) => [$dependency->source, $dependency->required], $source->dependencies()))->toBe([
            ['users', true], ['quotes', true], ['work-orders', true], ['registries', false],
        ]);
});
