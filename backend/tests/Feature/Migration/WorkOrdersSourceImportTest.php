<?php

use App\Enums\MigrationStatus;
use App\Enums\WorkOrderType;
use App\Models\MigrationRun;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// seedMigrationsConfig/fakeMigrationsBaseUrl/migrationsSuperAdminActor/
// runMigrationJobFor live in tests/Helpers/MigrationHelpers.php.

if (! function_exists('legacyWorkOrderRecord')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function legacyWorkOrderRecord(array $overrides = []): array
    {
        return array_merge([
            'id' => 17,
            'quote_id' => 42,
            'title' => 'Legacy work order',
            'type' => 'project',
            'start_date' => '2024-05-01',
            'end_date' => null,
            'callback_date' => '2024-06-01',
            'description' => 'Legacy description',
            'notes' => 'Legacy notes',
            'status' => 1,
            'status_label' => 'Aperta',
            'quote_line_ids' => [],
            'supervisor_user_ids' => [],
            'participant_user_ids' => [],
            'created_at' => '2024-04-20 09:00:00',
            'updated_at' => '2024-04-21 09:00:00',
        ], $overrides);
    }
}

if (! function_exists('runLegacyWorkOrdersImport')) {
    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    function runLegacyWorkOrdersImport(array $records, ?User $actor = null): MigrationRun
    {
        Http::fake([
            fakeMigrationsBaseUrl().'/work-orders*' => Http::response([
                'items' => $records,
                'pagination' => ['total' => count($records)],
            ]),
        ]);

        $run = MigrationRun::factory()->create(['user_id' => ($actor ?? migrationsSuperAdminActor())->id, 'source' => 'work-orders']);
        runMigrationJobFor($run);

        return $run->fresh();
    }
}

if (! function_exists('legacyWorkOrderWarnings')) {
    /**
     * @return array<int, string>
     */
    function legacyWorkOrderWarnings(MigrationRun $run): array
    {
        return collect($run->report)->where('level', 'warning')->pluck('message')->values()->all();
    }
}

beforeEach(function () {
    seedMigrationsConfig();
    $this->quote = Quote::factory()->create(['old_id' => 42]);
});

it('creates the work order with its lines, supervisors, participants and legacy anchors', function () {
    Notification::fake();
    $line = QuoteLine::factory()->create(['quote_id' => $this->quote->id, 'old_id' => 900]);
    $supervisor = User::factory()->create(['old_id' => 61]);
    $firstParticipant = User::factory()->create(['old_id' => 62]);
    $secondParticipant = User::factory()->create(['old_id' => 63]);
    $actor = migrationsSuperAdminActor();
    $activityBefore = DB::table('activity_log')->count();

    $run = runLegacyWorkOrdersImport([legacyWorkOrderRecord([
        'end_date' => '2024-12-31',
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

it('force-closes the closed legacy statuses with their legacy reason', function (int $legacyStatus, string $reason) {
    runLegacyWorkOrdersImport([legacyWorkOrderRecord(['status' => $legacyStatus])]);

    $workOrder = WorkOrder::query()->where('old_id', 17)->sole();

    expect($workOrder->is_force_closed)->toBeTrue()
        ->and($workOrder->force_close_reason)->toBe($reason);
})->with([
    'closed' => [3, 'Chiusa (legacy)'],
    'cancelled' => [4, 'Annullata (legacy)'],
    'terminated' => [5, 'Disdetta (legacy)'],
]);

it('keeps a line already programmed into another work order there, with a warning', function () {
    $taken = QuoteLine::factory()->create(['quote_id' => $this->quote->id, 'old_id' => 900, 'sort_order' => 0]);
    $free = QuoteLine::factory()->create(['quote_id' => $this->quote->id, 'old_id' => 901, 'sort_order' => 1]);
    $other = WorkOrder::factory()->create(['quote_id' => $this->quote->id]);
    $other->quoteLines()->attach($taken->id);

    $run = runLegacyWorkOrdersImport([legacyWorkOrderRecord(['quote_line_ids' => [900, 901]])]);

    $workOrder = WorkOrder::query()->where('old_id', 17)->sole();

    expect($run->created_rows)->toBe(1)
        ->and($workOrder->quoteLines()->pluck('quote_lines.id')->all())->toBe([$free->id])
        ->and($other->quoteLines()->pluck('quote_lines.id')->all())->toBe([$taken->id])
        ->and(legacyWorkOrderWarnings($run))->toContain('Quote line (legacy id 900) already belongs to another work order; not linked.');
});

it('replaces a missing or implausible start date with the legacy creation date', function (?string $startDate) {
    $run = runLegacyWorkOrdersImport([legacyWorkOrderRecord(['start_date' => $startDate])]);

    expect(WorkOrder::query()->where('old_id', 17)->sole()->start_date->toDateString())->toBe('2024-04-20')
        ->and(legacyWorkOrderWarnings($run))->toHaveCount(1);
})->with([
    'missing' => [null],
    'placeholder year' => ['1970-01-01'],
]);

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
