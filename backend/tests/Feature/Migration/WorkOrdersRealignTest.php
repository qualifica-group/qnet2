<?php

use App\Enums\AttributeContext;
use App\Enums\WorkOrderWorkPhase;
use App\Models\Attribute;
use App\Models\MigrationRun;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderLinePayment;
use App\Models\WorkOrderPaymentStatus;
use App\Models\WorkOrderStage;
use App\Models\WorkOrderWorkflowStatus;
use Database\Seeders\WorkOrderPaymentStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/**
 * Spec 0372, M3: realignment of already imported commesse (AC-001..AC-010, AC-019).
 * Fixtures come from tests/Helpers/WorkOrderMigrationHelpers.php (real legacy shape).
 */
uses(RefreshDatabase::class);

/** The first matching stub wins: a second fake for the same URL would never be reached. */
function resetHttpFakes(): void
{
    Http::swap(new HttpFactory);
}

/**
 * @param  array<int, array<string, mixed>>  $records
 */
function realignLegacyWorkOrders(array $records, bool $updateExisting = true): MigrationRun
{
    resetHttpFakes();
    Http::fake([
        fakeMigrationsBaseUrl().'/work-orders*' => Http::response(['items' => $records, 'pagination' => ['total' => count($records)]]),
    ]);

    $run = MigrationRun::factory()->create([
        'user_id' => migrationsSuperAdminActor()->id,
        'source' => 'work-orders',
        'update_existing' => $updateExisting,
    ]);
    runMigrationJobFor($run);

    return $run->fresh();
}

beforeEach(function () {
    seedMigrationsConfig();
    $this->seed(WorkOrderPaymentStatusSeeder::class);
    $this->quote = Quote::factory()->create(['old_id' => 42]);
    $this->line = QuoteLine::factory()->create(['quote_id' => $this->quote->id, 'old_id' => 900, 'sort_order' => 0]);
    Notification::fake();
});

it('AC-001: without the flag an existing work order is skipped without writes', function () {
    runLegacyWorkOrdersImport([legacyWorkOrderRecord()]);

    $run = realignLegacyWorkOrders([legacyWorkOrderRecord(['title' => 'Changed in legacy'])], updateExisting: false);

    expect($run->skipped_rows)->toBe(1)
        ->and($run->updated_rows)->toBe(0)
        ->and(WorkOrder::query()->where('old_id', 17)->value('title'))->toBe('Legacy work order');
});

it('AC-002: realigns the scalar fields, sets the legacy updated_at and logs nothing', function () {
    runLegacyWorkOrdersImport([legacyWorkOrderRecord()]);
    $maxActivityId = (int) DB::table('activity_log')->max('id');

    $run = realignLegacyWorkOrders([legacyWorkOrderRecord([
        'title' => 'Renamed',
        'start_date' => '2024-07-01',
        'callback_date' => '2024-08-01',
        'description' => 'New description',
        'internal_notes' => 'New notes',
        'force_close_reason' => 'Cliente rinuncia',
        'updated_at' => '2025-01-10 10:00:00',
    ])]);

    $workOrder = WorkOrder::query()->where('old_id', 17)->sole();

    expect($run->updated_rows)->toBe(1)
        ->and($run->failed_rows)->toBe(0)
        ->and($workOrder->title)->toBe('Renamed')
        ->and($workOrder->title_is_manual)->toBeTrue()
        ->and($workOrder->start_date->toDateString())->toBe('2024-07-01')
        ->and($workOrder->callback_date->toDateString())->toBe('2024-08-01')
        ->and($workOrder->description)->toBe('New description')
        ->and($workOrder->internal_notes)->toBe('New notes')
        ->and($workOrder->is_force_closed)->toBeTrue()
        ->and($workOrder->force_close_reason)->toBe('Cliente rinuncia')
        ->and($workOrder->code)->toBe('COM-0017')
        ->and($workOrder->updated_at->toDateTimeString())->toBe('2025-01-10 10:00:00')
        ->and(DB::table('activity_log')->where('id', '>', $maxActivityId)->where('subject_type', 'like', 'work_order%')->pluck('log_name')->all())->toBe([]);
});

it('spec 0395 AC-006: realigns the legacy working phase of an untouched work order', function () {
    runLegacyWorkOrdersImport([legacyWorkOrderRecord()]);

    $run = realignLegacyWorkOrders([legacyWorkOrderRecord(['work_phase' => 'terminated', 'work_phase_before_termination' => 'to_plan'])]);

    $workOrder = WorkOrder::query()->where('old_id', 17)->sole();

    expect($run->updated_rows)->toBe(1)
        ->and($workOrder->work_phase)->toBe(WorkOrderWorkPhase::Terminated)
        ->and($workOrder->work_phase_before_termination)->toBe(WorkOrderWorkPhase::ToPlan);
});

it('AC-003: realigns supervisors and participants without notifications', function () {
    User::factory()->create(['old_id' => 61]);
    $newSupervisor = User::factory()->create(['old_id' => 64]);
    $first = User::factory()->create(['old_id' => 62]);
    $second = User::factory()->create(['old_id' => 63]);
    runLegacyWorkOrdersImport([legacyWorkOrderRecord(['supervisor_user_ids' => [61], 'participant_user_ids' => [62]])]);
    $maxActivityId = (int) DB::table('activity_log')->max('id');

    $run = realignLegacyWorkOrders([legacyWorkOrderRecord([
        'supervisor_user_ids' => [64],
        'participant_user_ids' => [63, 62],
        'updated_at' => '2025-01-10 10:00:00',
    ])]);

    $workOrder = WorkOrder::query()->where('old_id', 17)->sole();
    $participants = $workOrder->participants()->get()->mapWithKeys(fn (User $user) => [$user->id => (int) $user->pivot->position])->all();

    expect($run->updated_rows)->toBe(1)
        ->and($workOrder->supervisors()->pluck('users.id')->all())->toBe([$newSupervisor->id])
        ->and($participants)->toBe([$second->id => 1, $first->id => 2])
        ->and(DB::table('activity_log')->where('id', '>', $maxActivityId)->where('subject_type', 'like', 'work_order%')->count())->toBe(0);

    Notification::assertNothingSent();
});

it('AC-004: a work order closed in the legacy is closed without touching its open tasks', function () {
    runLegacyWorkOrdersImport([legacyWorkOrderRecord()]);
    $workOrder = WorkOrder::query()->where('old_id', 17)->sole();
    $task = Task::factory()->create(['work_order_id' => $workOrder->id]);
    $statusBefore = $task->fresh()->getAttributes();

    $run = realignLegacyWorkOrders([legacyWorkOrderRecord(['force_close_reason' => 'Chiusa dal cliente'])]);

    expect($run->updated_rows)->toBe(1)
        ->and($workOrder->fresh()->is_force_closed)->toBeTrue()
        ->and($workOrder->fresh()->force_close_reason)->toBe('Chiusa dal cliente')
        ->and($task->fresh()->getAttributes())->toBe($statusBefore);
});

it('AC-005: an unmigrated supervisor fails the row and writes nothing', function () {
    runLegacyWorkOrdersImport([legacyWorkOrderRecord()]);

    $run = realignLegacyWorkOrders([legacyWorkOrderRecord(['title' => 'Renamed', 'supervisor_user_ids' => [999]])]);

    expect($run->failed_rows)->toBe(1)
        ->and($run->updated_rows)->toBe(0)
        ->and(collect($run->report)->firstWhere('level', 'error')['message'])->toContain('Supervisor (legacy user id 999) not migrated')
        ->and(WorkOrder::query()->where('old_id', 17)->value('title'))->toBe('Legacy work order');
});

it('AC-006: creates a new stage, closes one, keeps the missing one with a warning', function () {
    runLegacyWorkOrdersImport([legacyWorkOrderRecord(['stages' => [
        ['name' => 'Analisi', 'closed_at' => null, 'closed_by_user_id' => null],
        ['name' => 'Vecchia', 'closed_at' => null, 'closed_by_user_id' => null],
    ]])]);

    $run = realignLegacyWorkOrders([legacyWorkOrderRecord(['stages' => [
        ['name' => 'Analisi', 'closed_at' => '2025-01-05 09:00:00', 'closed_by_user_id' => null],
        ['name' => 'Consegna', 'closed_at' => null, 'closed_by_user_id' => null],
    ]])]);

    $stages = WorkOrderStage::query()->get()->keyBy('name');

    expect($run->updated_rows)->toBe(1)
        ->and($stages)->toHaveCount(3)
        ->and($stages['Analisi']->closed_at->toDateTimeString())->toBe('2025-01-05 09:00:00')
        ->and($stages['Consegna']->closed_at)->toBeNull()
        ->and($stages->has('Vecchia'))->toBeTrue()
        ->and(legacyWorkOrderWarnings($run))->toBe(['Stage Vecchia is no longer in the legacy; kept.']);
});

it('AC-006: reports a stage the legacy no longer sends even when nothing else changes', function () {
    runLegacyWorkOrdersImport([legacyWorkOrderRecord(['stages' => [
        ['name' => 'Analisi', 'closed_at' => null, 'closed_by_user_id' => null],
        ['name' => 'Vecchia', 'closed_at' => null, 'closed_by_user_id' => null],
    ]])]);

    $run = realignLegacyWorkOrders([legacyWorkOrderRecord(['stages' => [
        ['name' => 'Analisi', 'closed_at' => null, 'closed_by_user_id' => null],
    ]])]);

    expect($run->skipped_rows)->toBe(1)
        ->and($run->updated_rows)->toBe(0)
        ->and(WorkOrderStage::query()->count())->toBe(2)
        ->and(legacyWorkOrderWarnings($run))->toBe(['Stage Vecchia is no longer in the legacy; kept.']);
});

it('AC-007: a different payment status lands on every line without duplicate rows', function () {
    $second = QuoteLine::factory()->create(['quote_id' => $this->quote->id, 'old_id' => 901, 'sort_order' => 1]);
    runLegacyWorkOrdersImport([legacyWorkOrderRecord(['quote_line_ids' => [900, 901], 'stato_pagamento' => 3])]);

    $run = realignLegacyWorkOrders([legacyWorkOrderRecord(['quote_line_ids' => [900, 901], 'stato_pagamento' => 1, 'accordo_pagamento' => 'Bonifico'])]);

    $payments = WorkOrderLinePayment::query()->get();

    expect($run->updated_rows)->toBe(1)
        ->and($payments)->toHaveCount(2)
        ->and($payments->pluck('work_order_payment_status_id')->unique()->all())->toBe([WorkOrderPaymentStatus::where('old_id', 1)->value('id')])
        ->and($payments->pluck('payment_agreement')->unique()->all())->toBe(['Bonifico'])
        ->and($payments->pluck('quote_line_id')->sort()->values()->all())->toBe([$this->line->id, $second->id]);
});

it('AC-008: updates a flexible field and keeps a code the legacy no longer sends', function () {
    $category = $this->line->product->category;

    foreach (['scopo', 'note_extra'] as $code) {
        $attribute = Attribute::factory()->create(['code' => $code, 'type' => 'textarea']);
        $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => AttributeContext::WorkOrder->value]);
    }

    runLegacyWorkOrdersImport([legacyWorkOrderRecord(['attribute_values' => ['scopo' => 'Old scope', 'note_extra' => 'Kept']])]);

    $run = realignLegacyWorkOrders([legacyWorkOrderRecord(['attribute_values' => ['scopo' => 'New scope']])]);

    expect($run->updated_rows)->toBe(1)
        ->and(WorkOrder::query()->where('old_id', 17)->sole()->attribute_values)->toBe(['scopo' => 'New scope', 'note_extra' => 'Kept']);
});

it('AC-009: a work order with activity on itself, a stage or a payment is not updated', function (string $touch) {
    runLegacyWorkOrdersImport([legacyWorkOrderRecord(['stages' => [['name' => 'Analisi', 'closed_at' => null, 'closed_by_user_id' => null]]])]);
    $workOrder = WorkOrder::query()->where('old_id', 17)->sole();

    $subject = match ($touch) {
        'work order' => $workOrder,
        'stage' => WorkOrderStage::query()->sole(),
        'payment' => WorkOrderLinePayment::query()->sole(),
    };
    activity()->performedOn($subject)->log('manual change');

    $run = realignLegacyWorkOrders([legacyWorkOrderRecord(['title' => 'Renamed'])]);

    expect($run->skipped_rows)->toBe(1)
        ->and($run->updated_rows)->toBe(0)
        ->and(legacyWorkOrderWarnings($run))->toBe(['Changed in qnet-2 after the import; not updated.'])
        ->and($workOrder->fresh()->title)->toBe('Legacy work order');
})->with(['work order', 'stage', 'payment']);

it('AC-010: an identical work order is skipped without writes and without warnings', function () {
    User::factory()->create(['old_id' => 61]);
    User::factory()->create(['old_id' => 62]);
    $attribute = Attribute::factory()->create(['code' => 'scopo', 'type' => 'textarea']);
    $this->line->product->category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => AttributeContext::WorkOrder->value]);
    $record = legacyWorkOrderRecord([
        'stages' => [['name' => 'Analisi', 'closed_at' => null, 'closed_by_user_id' => null]],
        'stato_pagamento' => 3,
        'supervisor_user_ids' => [61],
        'participant_user_ids' => [62],
        'attribute_values' => ['scopo' => 'Same scope'],
    ]);
    runLegacyWorkOrdersImport([$record]);
    $before = WorkOrder::query()->where('old_id', 17)->sole()->updated_at->toDateTimeString();

    $run = realignLegacyWorkOrders([$record]);

    expect($run->skipped_rows)->toBe(1)
        ->and($run->updated_rows)->toBe(0)
        ->and($run->report)->toBeNull()
        ->and(WorkOrder::query()->where('old_id', 17)->sole()->updated_at->toDateTimeString())->toBe($before);
});

it('AC-019: the workflow status source skips a touched work order with the flag and acts as before without it', function () {
    runLegacyWorkOrdersImport([legacyWorkOrderRecord()]);
    $workOrder = WorkOrder::query()->where('old_id', 17)->sole();
    $status = WorkOrderWorkflowStatus::factory()->create(['work_order_workflow_id' => null]);
    $status->forceFill(['old_id' => 10])->save();
    activity()->performedOn($workOrder)->log('manual change');
    $before = $workOrder->fresh()->work_order_workflow_status_id;

    $runStatus = function (bool $updateExisting) {
        resetHttpFakes();
        Http::fake([
            fakeMigrationsBaseUrl().'/work-order-workflow-statuses*' => Http::response([
                'items' => [['id' => 17, 'status_id' => 10, 'warnings' => []]],
                'pagination' => ['total' => 1],
            ]),
        ]);
        $run = MigrationRun::factory()->create([
            'user_id' => migrationsSuperAdminActor()->id,
            'source' => 'work-order-workflow-statuses',
            'update_existing' => $updateExisting,
        ]);
        runMigrationJobFor($run);

        return $run->fresh();
    };

    $withFlag = $runStatus(true);

    expect($withFlag->skipped_rows)->toBe(1)
        ->and(collect($withFlag->report)->pluck('message')->all())->toBe(['Changed in qnet-2 after the import; not updated.'])
        ->and($workOrder->fresh()->work_order_workflow_status_id)->toBe($before);

    $without = $runStatus(false);

    expect($without->created_rows)->toBe(1)
        ->and($without->updated_rows)->toBe(0)
        ->and($workOrder->fresh()->work_order_workflow_status_id)->toBe($status->id);
});
