<?php

use App\Migrations\Realign\LegacyRealignReport;
use App\Migrations\Realign\LegacyRealignRunner;
use App\Migrations\Realign\Targets\OpportunitiesTarget;
use App\Models\MigrationRun;
use App\Models\Opportunity;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

// Spec 0375 AC-012 on the opportunity (spec 0372 AC-017, AC-018).

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function realignOpportunityRecord(array $overrides = []): array
{
    return [
        'id' => 950, 'registry_id' => 20, 'title' => 'Rinnovo 2027', 'referent_id' => null,
        'commercial_referent_id' => null, 'reporter_referent_id' => null, 'supervisor_user_id' => 701,
        'manager_user_ids' => [702], 'operational_site_id' => null, 'product_category_ids' => [],
        'start_date' => '2026-02-01', 'expected_close_date' => '2026-09-30', 'estimated_value' => 5000.0,
        'general_notes' => 'Nota legacy', 'preliminary_info_blocks' => [], 'status' => 1, 'status_label' => 'Aperta',
        'created_at' => '2026-01-10 09:00:00', 'updated_at' => '2026-11-05 10:00:00', ...$overrides,
    ];
}

function realignImportOpportunity(): Opportunity
{
    Http::fake([fakeMigrationsBaseUrl().'/opportunities*' => Http::response(['items' => [realignOpportunityRecord()], 'pagination' => ['total' => 1]])]);
    $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'opportunities']);
    runMigrationJobFor($run);

    return Opportunity::query()->where('old_id', 950)->firstOrFail();
}

/**
 * @param  array<string, mixed>  $overrides
 */
function realignOpportunityTarget(array $overrides, bool $apply = true): LegacyRealignReport
{
    Http::swap(new HttpFactory);
    fakeLegacyRealign('opportunities', [950], [], [realignOpportunityRecord($overrides)]);
    $report = new LegacyRealignReport;
    app(LegacyRealignRunner::class)->run(app(OpportunitiesTarget::class), '2026-10-23', $apply, $report);

    return $report;
}

beforeEach(function () {
    seedMigrationsConfig();
    Notification::fake();
    Registry::factory()->create(['old_id' => 20]);
    User::factory()->create(['old_id' => 701, 'name' => 'Old Supervisor']);
    User::factory()->create(['old_id' => 702, 'name' => 'Old Manager']);
    $this->newManager = User::factory()->create(['old_id' => 704, 'name' => 'New Manager']);
    $this->opportunity = realignImportOpportunity();
});

it('AC-012: aligns name, dates, value, notes and managers, by field', function () {
    $report = realignOpportunityTarget(['title' => 'Rinnovo 2028', 'estimated_value' => 7000.0, 'general_notes' => 'Nuova', 'manager_user_ids' => [704]]);

    $opportunity = $this->opportunity->fresh();

    expect($opportunity->name)->toBe('Rinnovo 2028')
        ->and((float) $opportunity->estimated_value)->toBe(7000.0)
        ->and($opportunity->managers()->pluck('users.id')->all())->toBe([$this->newManager->id])
        ->and(collect($report->changes)->pluck(4)->all())->toContain('name', 'estimated_value', 'general_notes', 'managers');

    Notification::assertNothingSent();
});

it('AC-012: a simulation leaves the opportunity as it is', function () {
    $report = realignOpportunityTarget(['title' => 'Rinnovo 2028'], apply: false);

    expect($this->opportunity->fresh()->name)->toBe('Rinnovo 2027')
        ->and($report->counters['opportunities'][LegacyRealignReport::ALIGNED])->toBe(1);
});

it('AC-012: an opportunity changed on qnet-2 is left alone', function () {
    Activity::query()->delete();
    activity()->performedOn($this->opportunity)->event('updated')->log('updated');

    $report = realignOpportunityTarget(['title' => 'Rinnovo 2028']);

    expect($this->opportunity->fresh()->name)->toBe('Rinnovo 2027')
        ->and($report->counters['opportunities'][LegacyRealignReport::SKIPPED_TOUCHED])->toBe(1);
});
