<?php

use App\Enums\AttributeContext;
use App\Migrations\Realign\LegacyRealignReport;
use App\Migrations\Realign\LegacyRealignRunner;
use App\Migrations\Realign\Targets\WorkOrdersTarget;
use App\Models\Attribute;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderLinePayment;
use App\Models\WorkOrderPaymentStatus;
use App\Models\WorkOrderStage;
use Database\Seeders\WorkOrderPaymentStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

// Spec 0375 AC-008, AC-011: the commessa target on the 0372 realigner. Fixtures mirror the legacy contract.

/**
 * @param  array<string, mixed>  $legacy
 */
function realignLegacyWorkOrderRecord(array $legacy, bool $apply): LegacyRealignReport
{
    Http::swap(new HttpFactory);
    fakeLegacyRealign('work-orders', [17], [], [$legacy]);
    $report = new LegacyRealignReport;
    app(LegacyRealignRunner::class)->run(app(WorkOrdersTarget::class), '2026-10-23', $apply, $report);

    return $report;
}

beforeEach(function () {
    seedMigrationsConfig();
    $this->seed(WorkOrderPaymentStatusSeeder::class);
    $this->quote = Quote::factory()->create(['old_id' => 42]);
    $this->line = QuoteLine::factory()->create(['quote_id' => $this->quote->id, 'old_id' => 900, 'sort_order' => 0]);
    Notification::fake();
});

it('AC-008: a commessa with equal repeater rows has no difference and keeps the row ids', function () {
    $attribute = Attribute::factory()->create(['code' => 'orderiso_verificas', 'type' => 'table', 'config' => [
        'columns' => [
            ['key' => 'data_verifica', 'label' => 'Data', 'type' => 'date', 'required' => true],
            ['key' => 'ispettore_verifica', 'label' => 'Ispettore', 'type' => 'text', 'required' => true],
        ],
    ]]);
    $this->line->product->category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => AttributeContext::WorkOrder->value]);
    $values = ['orderiso_verificas' => ['rows' => [['data_verifica' => '2026-06-30', 'ispettore_verifica' => 'Franciosi']]]];
    runLegacyWorkOrdersImport([legacyWorkOrderRecord(['attribute_values' => $values])]);
    $workOrder = WorkOrder::query()->where('old_id', 17)->sole();
    $before = $workOrder->attribute_values;
    $updatedAt = $workOrder->getRawOriginal('updated_at');

    $report = realignLegacyWorkOrderRecord(legacyWorkOrderRecord(['attribute_values' => $values, 'updated_at' => '2026-11-01 10:00:00']), apply: true);

    $workOrder = $workOrder->fresh();

    expect($report->changes)->toBe([])
        ->and($report->counters['work-orders'][LegacyRealignReport::UNCHANGED])->toBe(1)
        ->and($workOrder->attribute_values)->toBe($before)
        ->and($workOrder->getRawOriginal('updated_at'))->toBe($updatedAt);
});

it('AC-011: payment status, people, stages and flexible fields are aligned and reported by field', function () {
    $category = $this->line->product->category;
    $attribute = Attribute::factory()->create(['code' => 'scopo', 'type' => 'textarea']);
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => AttributeContext::WorkOrder->value]);
    User::factory()->create(['old_id' => 61, 'name' => 'Old Supervisor']);
    $supervisor = User::factory()->create(['old_id' => 64, 'name' => 'New Supervisor']);
    $participant = User::factory()->create(['old_id' => 63, 'name' => 'New Participant']);
    runLegacyWorkOrdersImport([legacyWorkOrderRecord([
        'supervisor_user_ids' => [61], 'stato_pagamento' => 3, 'attribute_values' => ['scopo' => 'Old'],
        'stages' => [['name' => 'Analisi', 'closed_at' => null, 'closed_by_user_id' => null]],
    ])]);
    Activity::query()->delete();

    $report = realignLegacyWorkOrderRecord(legacyWorkOrderRecord([
        'title' => 'Renamed',
        'supervisor_user_ids' => [64],
        'participant_user_ids' => [63],
        'stato_pagamento' => 1,
        'accordo_pagamento' => 'Bonifico',
        'attribute_values' => ['scopo' => 'New'],
        'stages' => [
            ['name' => 'Analisi', 'closed_at' => '2026-10-30 09:00:00', 'closed_by_user_id' => null],
            ['name' => 'Consegna', 'closed_at' => null, 'closed_by_user_id' => null],
        ],
        'updated_at' => '2026-11-01 10:00:00',
    ]), apply: true);

    $workOrder = WorkOrder::query()->where('old_id', 17)->sole();

    expect($workOrder->title)->toBe('Renamed')
        ->and($workOrder->supervisors()->pluck('users.id')->all())->toBe([$supervisor->id])
        ->and($workOrder->participants()->pluck('users.id')->all())->toBe([$participant->id])
        ->and(WorkOrderLinePayment::query()->sole()->work_order_payment_status_id)->toBe(WorkOrderPaymentStatus::where('old_id', 1)->value('id'))
        ->and($workOrder->attribute_values['scopo'])->toBe('New')
        ->and(WorkOrderStage::query()->count())->toBe(2)
        ->and(collect($report->changes)->pluck(4)->all())->toContain('title', 'supervisors', 'participants', 'stages', 'payments', 'attribute_values');

    Notification::assertNothingSent();
    expect(Activity::query()->where('subject_type', (new WorkOrder)->getMorphClass())->count())->toBe(0);
});

it('counts a stage or a payment changed on qnet-2 as a change to the commessa', function (string $touched) {
    runLegacyWorkOrdersImport([legacyWorkOrderRecord(['stages' => [['name' => 'Analisi', 'closed_at' => null, 'closed_by_user_id' => null]]])]);
    $subject = $touched === 'stage' ? WorkOrderStage::query()->sole() : WorkOrderLinePayment::query()->sole();
    activity()->performedOn($subject)->event('updated')->log('updated');

    $report = realignLegacyWorkOrderRecord(legacyWorkOrderRecord(['title' => 'Renamed', 'stages' => [['name' => 'Analisi', 'closed_at' => null, 'closed_by_user_id' => null]]]), apply: true);

    expect(WorkOrder::query()->where('old_id', 17)->value('title'))->toBe('Legacy work order')
        ->and($report->counters['work-orders'][LegacyRealignReport::SKIPPED_TOUCHED])->toBe(1);
})->with(['stage', 'payment']);
