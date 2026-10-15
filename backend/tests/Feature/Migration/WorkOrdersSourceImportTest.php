<?php

use App\Enums\CommissionType;
use App\Enums\MigrationStatus;
use App\Enums\WorkOrderType;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteLineCommission;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// seedMigrationsConfig/fakeMigrationsBaseUrl/migrationsSuperAdminActor/
// runMigrationJobFor live in tests/Helpers/MigrationHelpers.php; the legacy
// work order fixtures in tests/Helpers/WorkOrderMigrationHelpers.php.

beforeEach(function () {
    seedMigrationsConfig();
    $this->quote = Quote::factory()->create(['old_id' => 42]);
    $this->line = QuoteLine::factory()->create(['quote_id' => $this->quote->id, 'old_id' => 900, 'sort_order' => 0]);
});

it('creates the work order with its lines, supervisors, participants and legacy anchors', function () {
    Notification::fake();
    $line = $this->line;
    $supervisor = User::factory()->create(['old_id' => 61]);
    $firstParticipant = User::factory()->create(['old_id' => 62]);
    $secondParticipant = User::factory()->create(['old_id' => 63]);
    $actor = migrationsSuperAdminActor();
    $activityBefore = DB::table('activity_log')->count();

    $run = runLegacyWorkOrdersImport([legacyWorkOrderRecord([
        'internal_notes' => "Legacy notes\n\nData fine (legacy): 31/12/2024",
        'quote_line_ids' => [900],
        'supervisor_user_ids' => [61],
        'participant_user_ids' => [62, 63],
    ])], $actor);

    $workOrder = WorkOrder::query()->where('old_id', 17)->sole();
    $participants = $workOrder->participants()->get()->mapWithKeys(fn (User $user) => [$user->id => (int) $user->pivot->position])->all();

    expect($run->status)->toBe(MigrationStatus::Completed)
        ->and($run->created_rows)->toBe(1)
        ->and($run->report)->toBeNull()
        ->and($workOrder->code)->toBe('COM-0017')
        ->and($workOrder->quote_id)->toBe($this->quote->id)
        ->and($workOrder->title)->toBe('Legacy work order')
        ->and($workOrder->type)->toBe(WorkOrderType::Project)
        ->and($workOrder->start_date->toDateString())->toBe('2024-05-01')
        ->and($workOrder->callback_date->toDateString())->toBe('2024-06-01')
        ->and($workOrder->description)->toBe('Legacy description')
        ->and($workOrder->internal_notes)->toBe("Legacy notes\n\nData fine (legacy): 31/12/2024")
        ->and($workOrder->is_force_closed)->toBeFalse()
        ->and($workOrder->task_template_id)->toBeNull()
        ->and($workOrder->created_at->toDateTimeString())->toBe('2024-04-20 09:00:00')
        ->and($workOrder->quoteLines()->pluck('quote_lines.id')->all())->toBe([$line->id])
        ->and($workOrder->supervisors()->pluck('users.id')->all())->toBe([$supervisor->id])
        ->and($participants)->toBe([$firstParticipant->id => 1, $secondParticipant->id => 2])
        ->and(Task::query()->count())->toBe(0)
        ->and(WorkOrderStage::query()->count())->toBe(0)
        ->and(DB::table('activity_log')->count())->toBe($activityBefore);

    Notification::assertNothingSent();
});

it('falls back to a sequential code with a warning when COM-{id} is taken', function () {
    WorkOrder::factory()->create(['quote_id' => $this->quote->id])->forceFill(['code' => 'COM-0017'])->save();

    $run = runLegacyWorkOrdersImport([legacyWorkOrderRecord()]);

    expect(WorkOrder::query()->where('old_id', 17)->value('code'))->not->toBe('COM-0017')
        ->and(legacyWorkOrderWarnings($run))->toContain('Code COM-0017 already taken; a sequential code was generated.');
});

it('force-closes a work order the legacy sends with a force-close reason', function () {
    runLegacyWorkOrdersImport([legacyWorkOrderRecord(['force_close_reason' => 'Annullata (legacy)'])]);

    $workOrder = WorkOrder::query()->where('old_id', 17)->sole();

    expect($workOrder->is_force_closed)->toBeTrue()
        ->and($workOrder->force_close_reason)->toBe('Annullata (legacy)');
});

it('links a copy of a line already programmed into another work order, with its commissions and the offer totals', function () {
    $taken = tap($this->line)->update([
        'quantity' => 1, 'unit_price' => 900, 'net_amount' => 900, 'vat_amount' => 198, 'total_amount' => 1098,
    ]);
    QuoteLineCommission::factory()->create([
        'quote_line_id' => $taken->id, 'commission_type' => CommissionType::Percentage, 'value' => '10',
    ]);
    $free = QuoteLine::factory()->create(['quote_id' => $this->quote->id, 'old_id' => 901, 'sort_order' => 1]);
    $other = WorkOrder::factory()->create(['quote_id' => $this->quote->id]);
    $other->quoteLines()->attach($taken->id);
    $revenueBefore = (float) QuoteLine::query()->where('quote_id', $this->quote->id)->sum('net_amount');

    $run = runLegacyWorkOrdersImport([legacyWorkOrderRecord(['quote_line_ids' => [900, 901]])]);

    $workOrder = WorkOrder::query()->where('old_id', 17)->sole();
    $copy = QuoteLine::query()->where('quote_id', $this->quote->id)->whereNotIn('id', [$taken->id, $free->id])->sole();

    expect($run->created_rows)->toBe(1)
        ->and($copy->old_id)->toBeNull()
        ->and($copy->product_id)->toBe($taken->product_id)
        ->and((float) $copy->net_amount)->toBe(900.0)
        ->and($copy->sort_order)->toBe(2)
        ->and($copy->commissions()->count())->toBe(1)
        ->and($workOrder->quoteLines()->pluck('quote_lines.id')->sort()->values()->all())->toBe(collect([$free->id, $copy->id])->sort()->values()->all())
        ->and($other->quoteLines()->pluck('quote_lines.id')->all())->toBe([$taken->id])
        ->and((float) $this->quote->fresh()->revenue_net)->toBe(round($revenueBefore + 900.0, 2))
        ->and(legacyWorkOrderWarnings($run))->toContain('Quote line (legacy id 900) already belongs to another work order; duplicated on the offer (+900.00 net).');
});

it('fails the row without a start date', function () {
    $run = runLegacyWorkOrdersImport([legacyWorkOrderRecord(['start_date' => null])]);

    expect($run->failed_rows)->toBe(1)
        ->and(WorkOrder::query()->count())->toBe(0)
        ->and(collect($run->report)->firstWhere('level', 'error')['message'])->toContain('Start date is required.');
});

it('skips an already imported work order on a second run', function () {
    runLegacyWorkOrdersImport([legacyWorkOrderRecord()]);
    $second = runLegacyWorkOrdersImport([legacyWorkOrderRecord()]);

    expect($second->skipped_rows)->toBe(1)
        ->and($second->created_rows)->toBe(0)
        ->and(WorkOrder::query()->where('old_id', 17)->count())->toBe(1);
});

it('fails the row when the parent quote is not migrated', function () {
    $run = runLegacyWorkOrdersImport([legacyWorkOrderRecord(['quote_id' => 999])]);

    expect($run->failed_rows)->toBe(1)
        ->and(WorkOrder::query()->count())->toBe(0)
        ->and(collect($run->report)->firstWhere('level', 'error')['message'])
        ->toContain('Unresolved quote_id (legacy id 999)');
});
