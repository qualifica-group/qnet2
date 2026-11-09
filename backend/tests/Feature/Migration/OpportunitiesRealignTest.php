<?php

use App\Models\BusinessFunction;
use App\Models\MigrationRun;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// OpportunitiesSource with update_existing (spec 0372, M5): AC-001, AC-017, AC-018
// ---------------------------------------------------------------------------

/**
 * A legacy row as OpportunityMigrationController::mapOpportunity sends it.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function realignLegacyOpportunity(array $overrides = []): array
{
    return [
        'id' => 950, 'registry_id' => 20, 'title' => 'Rinnovo 2027', 'referent_id' => null,
        'commercial_referent_id' => null, 'reporter_referent_id' => null, 'supervisor_user_id' => 701,
        'manager_user_ids' => [702], 'operational_site_id' => null, 'product_category_ids' => [],
        'start_date' => '2026-02-01', 'expected_close_date' => '2026-09-30', 'estimated_value' => 5000.0,
        'general_notes' => 'Nota legacy', 'preliminary_info_blocks' => [], 'status' => 1, 'status_label' => 'Aperta',
        'created_at' => '2026-01-10 09:00:00', 'updated_at' => '2026-11-05 10:00:00',
        ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 */
function runRealignOpportunities(array $overrides, bool $updateExisting): MigrationRun
{
    seedMigrationsConfig();
    // A fresh factory: stubs of an earlier call in the same test would win over these.
    Http::swap(new Factory);
    Http::fake([
        fakeMigrationsBaseUrl().'/opportunities*' => Http::response(['items' => [realignLegacyOpportunity($overrides)], 'pagination' => ['total' => 1]]),
    ]);

    $run = MigrationRun::factory()->create([
        'user_id' => migrationsSuperAdminActor()->id, 'source' => 'opportunities', 'update_existing' => $updateExisting,
    ]);
    runMigrationJobFor($run);

    return $run->fresh();
}

/**
 * @return array{0: Opportunity, 1: User, 2: User, 3: Registry}
 */
function realignExistingOpportunity(): array
{
    $registry = Registry::factory()->create();
    $registry->forceFill(['old_id' => 20])->save();
    [$supervisor, $manager] = User::factory()->count(2)->create();
    $supervisor->forceFill(['old_id' => 701])->save();
    $manager->forceFill(['old_id' => 702])->save();

    $run = runRealignOpportunities([], false);
    $opportunity = Opportunity::query()->where('old_id', 950)->firstOrFail();

    expect($run->created_rows)->toBe(1);

    return [$opportunity, $supervisor, $manager, $registry];
}

it('realigns an untouched opportunity with the fields that differ, leaving anagrafica and categories alone', function () {
    Notification::fake();
    [$opportunity, $supervisor, $manager, $registry] = realignExistingOpportunity();
    $newSupervisor = User::factory()->create();
    $newSupervisor->forceFill(['old_id' => 703])->save();
    $newManager = User::factory()->create();
    $newManager->forceFill(['old_id' => 704])->save();
    $activityRows = DB::table('activity_log')->where('subject_type', 'opportunity')->count();

    $run = runRealignOpportunities([
        'title' => 'Rinnovo 2028', 'start_date' => '2026-03-01', 'expected_close_date' => '2026-12-31',
        'estimated_value' => 7500.5, 'general_notes' => 'Nota aggiornata', 'supervisor_user_id' => 703,
        'manager_user_ids' => [704, 702], 'updated_at' => '2026-11-08 12:00:00',
        'registry_id' => 999, 'product_category_ids' => [],
    ], true);

    $opportunity = $opportunity->fresh()->load('managers');

    expect($run->updated_rows)->toBe(1)
        ->and($run->created_rows)->toBe(0)
        ->and($run->skipped_rows)->toBe(0)
        ->and($opportunity->name)->toBe('Rinnovo 2028')
        ->and($opportunity->start_date->toDateString())->toBe('2026-03-01')
        ->and($opportunity->expected_close_date->toDateString())->toBe('2026-12-31')
        ->and($opportunity->estimated_value)->toBe('7500.50')
        ->and($opportunity->general_notes)->toBe('Nota aggiornata')
        ->and($opportunity->supervisor_id)->toBe($newSupervisor->id)
        ->and($opportunity->managers->pluck('pivot.position', 'id')->all())->toBe([$newManager->id => 1, $manager->id => 2])
        ->and($opportunity->registry_id)->toBe($registry->id)
        ->and($opportunity->updated_at->format('Y-m-d H:i:s'))->toBe('2026-11-08 12:00:00')
        ->and(DB::table('activity_log')->where('subject_type', 'opportunity')->count())->toBe($activityRows);

    Notification::assertNothingSent();
});

it('skips an identical untouched opportunity with no write and no mapping warning', function () {
    [$opportunity] = realignExistingOpportunity();
    $before = $opportunity->fresh()->updated_at->format('Y-m-d H:i:s');

    $run = runRealignOpportunities(['referent_id' => 4242], true);

    expect($run->skipped_rows)->toBe(1)
        ->and($run->updated_rows)->toBe(0)
        ->and($run->report ?? [])->toBe([])
        ->and($opportunity->fresh()->updated_at->format('Y-m-d H:i:s'))->toBe($before);
});

it('keeps a manager of one of its offers on the opportunity and removes one who is on no offer', function () {
    [$opportunity, , $manager] = realignExistingOpportunity();
    [$offerManager, $removedManager] = User::factory()->count(2)->create();
    $opportunity->managers()->attach($offerManager->id, ['position' => 2]);
    $opportunity->managers()->attach($removedManager->id, ['position' => 3]);
    Quote::factory()->create(['opportunity_id' => $opportunity->id])->managers()->attach($offerManager->id, ['position' => 1]);

    $run = runRealignOpportunities([], true);
    $secondRun = runRealignOpportunities([], true);

    expect($run->updated_rows)->toBe(1)
        ->and($opportunity->managers()->get()->pluck('pivot.position', 'id')->all())->toBe([$manager->id => 1, $offerManager->id => 2])
        ->and($secondRun->skipped_rows)->toBe(1)
        ->and($secondRun->updated_rows)->toBe(0);
});

it('keeps the current name when the legacy title is blank and the supervisor when it is not migrated', function () {
    [$opportunity, $supervisor] = realignExistingOpportunity();

    $run = runRealignOpportunities(['title' => '', 'supervisor_user_id' => 12345, 'estimated_value' => 1.0], true);

    expect($run->updated_rows)->toBe(1)
        ->and($opportunity->fresh()->name)->toBe('Rinnovo 2027')
        ->and($opportunity->fresh()->supervisor_id)->toBe($supervisor->id)
        ->and(collect($run->report)->pluck('message')->implode(' '))->toContain('supervisor_user_id');
});

it('does not touch an opportunity worked on in qnet-2 and reports it', function () {
    [$opportunity] = realignExistingOpportunity();
    activity()->performedOn($opportunity)->event('updated')->log('updated');
    $notes = $opportunity->fresh()->general_notes;

    $run = runRealignOpportunities(['title' => 'Altro', 'general_notes' => 'Diverso'], true);

    expect($run->skipped_rows)->toBe(1)
        ->and($run->updated_rows)->toBe(0)
        ->and($opportunity->fresh()->name)->toBe('Rinnovo 2027')
        ->and($opportunity->fresh()->general_notes)->toBe($notes)
        ->and(collect($run->report)->pluck('message')->implode(' '))->toContain('Changed in qnet-2 after the import; not updated.');
});

it('behaves as before without the checkbox: only the back-fill, nothing overwritten', function () {
    [$opportunity] = realignExistingOpportunity();
    $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()->create()->id]);
    $category->forceFill(['old_id' => 33])->save();

    $run = runRealignOpportunities(['title' => 'Altro', 'general_notes' => 'Diverso', 'product_category_ids' => [33]], false);

    expect($run->skipped_rows)->toBe(1)
        ->and($run->updated_rows)->toBe(0)
        ->and($opportunity->fresh()->name)->toBe('Rinnovo 2027')
        ->and($opportunity->fresh()->general_notes)->toBe('Nota legacy')
        ->and($opportunity->productLines()->count())->toBe(1);
});

it('still back-fills the missing categories on an untouched opportunity and counts it as updated', function () {
    realignExistingOpportunity();
    $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()->create()->id]);
    $category->forceFill(['old_id' => 33])->save();

    $run = runRealignOpportunities(['product_category_ids' => [33]], true);

    expect($run->updated_rows)->toBe(1)
        ->and(Opportunity::query()->where('old_id', 950)->firstOrFail()->productLines()->count())->toBe(1);
});
