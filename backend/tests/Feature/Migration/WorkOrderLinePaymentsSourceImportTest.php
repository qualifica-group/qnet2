<?php

use App\Enums\MigrationStatus;
use App\Migrations\MigrationOrder;
use App\Migrations\Sources\WorkOrderLinePaymentsSource;
use App\Models\MigrationRun;
use App\Models\QuoteLine;
use App\Models\WorkOrder;
use App\Models\WorkOrderLinePayment;
use App\Models\WorkOrderPaymentStatus;
use Database\Seeders\WorkOrderPaymentStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/**
 * `work-order-line-payments` source (spec 0201, D-5/D-14): AC-014.
 */
uses(RefreshDatabase::class);

/**
 * @param  array<int, array<string, mixed>>  $records
 */
function runLegacyLinePaymentsImport(array $records): MigrationRun
{
    Http::fake([
        fakeMigrationsBaseUrl().'/work-orders*' => Http::response([
            'items' => $records,
            'pagination' => ['total' => count($records)],
        ]),
    ]);

    $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'work-order-line-payments']);
    runMigrationJobFor($run);

    return $run->fresh();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function legacyLinePaymentRecord(array $overrides = []): array
{
    return ['id' => 17, 'stato_pagamento' => 3, 'accordo_pagamento' => 'Pagamento in due rate', 'insoluti' => 1, ...$overrides];
}

/** @return array{workOrder: WorkOrder, lines: array<int, QuoteLine>} */
function migratedWorkOrderWithLines(int $legacyId = 17, int $lineCount = 2): array
{
    $workOrder = WorkOrder::factory()->create(['old_id' => $legacyId]);
    $lines = QuoteLine::factory()->count($lineCount)->create(['quote_id' => $workOrder->quote_id])->all();
    $workOrder->quoteLines()->attach(array_map(fn (QuoteLine $line): int => $line->id, $lines));

    return compact('workOrder', 'lines');
}

beforeEach(function () {
    seedMigrationsConfig();
    $this->seed(WorkOrderPaymentStatusSeeder::class);
});

it('AC-014: copies status, agreement and unpaid flag onto every line, with no activity log and no notification', function () {
    Notification::fake();
    ['workOrder' => $workOrder, 'lines' => $lines] = migratedWorkOrderWithLines();

    $run = runLegacyLinePaymentsImport([legacyLinePaymentRecord()]);

    expect($run->status)->toBe(MigrationStatus::Completed)
        ->and($run->created_rows)->toBe(1)
        ->and($run->failed_rows)->toBe(0)
        ->and($run->report)->toBeNull()
        ->and(WorkOrderLinePayment::count())->toBe(2)
        ->and(DB::table('activity_log')->where('subject_type', 'work_order_line_payment')->count())->toBe(0);

    $statusId = WorkOrderPaymentStatus::where('old_id', 3)->value('id');

    foreach ($lines as $line) {
        $payment = WorkOrderLinePayment::firstWhere('quote_line_id', $line->id);
        expect($payment->work_order_id)->toBe($workOrder->id)
            ->and($payment->work_order_payment_status_id)->toBe($statusId)
            ->and($payment->payment_agreement)->toBe('Pagamento in due rate')
            ->and($payment->has_unpaid)->toBeTrue();
    }

    Notification::assertNothingSent();
});

it('AC-014: a re-run updates the existing records without duplicating', function () {
    ['lines' => $lines] = migratedWorkOrderWithLines();
    Http::fake([
        fakeMigrationsBaseUrl().'/work-orders*' => Http::sequence()
            ->push(['items' => [legacyLinePaymentRecord()], 'pagination' => ['total' => 1]])
            ->push(['items' => [legacyLinePaymentRecord(['stato_pagamento' => 9, 'accordo_pagamento' => '', 'insoluti' => 0])], 'pagination' => ['total' => 1]]),
    ]);

    foreach ([1, 2] as $_) {
        runMigrationJobFor(MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'work-order-line-payments']));
    }

    expect(WorkOrderLinePayment::count())->toBe(2);

    $payment = WorkOrderLinePayment::firstWhere('quote_line_id', $lines[0]->id);
    expect($payment->work_order_payment_status_id)->toBe(WorkOrderPaymentStatus::where('old_id', 9)->value('id'))
        ->and($payment->payment_agreement)->toBeNull()
        ->and($payment->has_unpaid)->toBeFalse();
});

it('AC-014: a work order that was not migrated fails the row with a message', function () {
    $run = runLegacyLinePaymentsImport([legacyLinePaymentRecord(['id' => 999])]);

    expect($run->failed_rows)->toBe(1)
        ->and($run->created_rows)->toBe(0)
        ->and(collect($run->report)->firstWhere('level', 'error')['message'])->toContain('Work order not migrated (legacy id 999)')
        ->and(WorkOrderLinePayment::count())->toBe(0);
});

it('AC-014: an unknown legacy status key is left empty with a warning, the rest is still copied', function () {
    migratedWorkOrderWithLines(lineCount: 1);

    $run = runLegacyLinePaymentsImport([legacyLinePaymentRecord(['stato_pagamento' => 77])]);

    $payment = WorkOrderLinePayment::sole();
    expect($run->failed_rows)->toBe(0)
        ->and($payment->work_order_payment_status_id)->toBeNull()
        ->and($payment->payment_agreement)->toBe('Pagamento in due rate')
        ->and(collect($run->report)->where('level', 'warning')->pluck('message')->all())
        ->toBe(['Unresolved payment status (legacy key 77); left empty.']);
});

it('AC-014: an empty legacy status stays empty without a warning', function () {
    migratedWorkOrderWithLines(lineCount: 1);

    $run = runLegacyLinePaymentsImport([legacyLinePaymentRecord(['stato_pagamento' => null, 'insoluti' => null])]);

    expect($run->report)->toBeNull()
        ->and(WorkOrderLinePayment::sole()->has_unpaid)->toBeFalse();
});

it('AC-014: a work order without lines is a warning, nothing is written', function () {
    WorkOrder::factory()->create(['old_id' => 17]);

    $run = runLegacyLinePaymentsImport([legacyLinePaymentRecord()]);

    expect($run->failed_rows)->toBe(0)
        ->and(WorkOrderLinePayment::count())->toBe(0)
        ->and(collect($run->report)->where('level', 'warning')->count())->toBe(1);
});

it('AC-014: the source is registered, ordered after work-orders and previews the expected columns', function () {
    expect(config('migrations.definitions'))->toHaveKey('work-order-line-payments')
        ->and(collect(MigrationOrder::phases())->flatten()->all())->toContain('work-order-line-payments');

    $source = app(WorkOrderLinePaymentsSource::class);

    expect(collect($source->columns())->pluck('id')->all())->toBe(['id', 'stato_pagamento', 'accordo_pagamento', 'insoluti']);
});
