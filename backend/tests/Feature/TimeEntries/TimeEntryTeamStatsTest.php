<?php

use App\Models\BusinessFunction;
use App\Models\EmploymentProfile;
use App\Models\TaskType;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| GET /api/time-entries/stats/team (spec 0122, D-10, AC-019/AC-020)
|--------------------------------------------------------------------------
| 2026-09-14 is a Monday — a single working day, so `working_days`/
| `daily_target_minutes` math stays trivial (base target * 1) in every case.
*/

if (! function_exists('timeEntryActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function timeEntryActorWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'exportMonthly', 'manageAll', 'viewAll'] as $ability) {
            Permission::findOrCreate("time-entries.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("time-entries.{$ability}");
        }

        return $user;
    }
}

it('AC-019: a responsabile sees items for a DIRECT and an INDIRECT subordinate, correct manager_ids, coverage, self excluded', function () {
    $manager = timeEntryActorWith(['viewAny']);
    $direct = User::factory()->create(['name' => 'Bob Direct']);
    $indirect = User::factory()->create(['name' => 'Carl Indirect']);
    EmploymentProfile::factory()->manager()->create(['user_id' => $manager->id]);
    EmploymentProfile::factory()->reportsTo($manager)->create([
        'user_id' => $direct->id,
        'job_description' => 'Developer',
        'standard_daily_minutes' => 480,
        'break_daily_minutes' => 0,
    ]);
    EmploymentProfile::factory()->reportsTo($direct)->create(['user_id' => $indirect->id]);

    $function = BusinessFunction::factory()->create(['manager_id' => $direct->id]);
    $function->users()->attach($direct->id);

    $typeA = TaskType::factory()->create();
    TimeEntry::factory()->forUser($direct)->onDate('2026-09-14')->create(['task_type_id' => $typeA->id, 'minutes' => 240]);

    Sanctum::actingAs($manager);
    $response = $this->getJson('/api/time-entries/stats/team?date_from=2026-09-14&date_to=2026-09-14')->assertOk();

    expect($response->json('data.is_full_list'))->toBeFalse();
    $items = $response->json('data.items');
    expect($items)->toHaveCount(2)
        ->and(collect($items)->pluck('user.id'))->not->toContain($manager->id);

    $byId = collect($items)->keyBy('user.id');
    $directItem = $byId->get($direct->id);
    $indirectItem = $byId->get($indirect->id);

    expect($directItem['manager_ids'])->toBe([$manager->id])
        ->and($directItem['job_description'])->toBe('Developer')
        ->and($directItem['business_functions'])->toBe([['id' => $function->id, 'name' => $function->name, 'is_manager' => true]])
        ->and($directItem['coverage'])->toBe(['percentage' => 50, 'working_days' => 1, 'tracked_days' => 1])
        ->and($directItem['primary_cluster']['task_type']['id'])->toBe($typeA->id)
        ->and($directItem['primary_cluster']['minutes'])->toBe(240)
        ->and($indirectItem['manager_ids'])->toBe([$direct->id])
        ->and($indirectItem['coverage'])->toBe(['percentage' => 0, 'working_days' => 1, 'tracked_days' => 0])
        ->and($indirectItem['primary_cluster'])->toBeNull();
});

it('AC-012: a member with two managers has manager_ids ascending, both managers see the item', function () {
    $managerA = timeEntryActorWith(['viewAny']);
    $managerB = timeEntryActorWith(['viewAny']);
    $member = User::factory()->create();
    EmploymentProfile::factory()->manager()->create(['user_id' => $managerA->id]);
    EmploymentProfile::factory()->manager()->create(['user_id' => $managerB->id]);
    EmploymentProfile::factory()->reportsTo($managerB, $managerA)->create(['user_id' => $member->id]);

    [$lowId, $highId] = collect([$managerA->id, $managerB->id])->sort()->values()->all();

    foreach ([$managerA, $managerB] as $manager) {
        Sanctum::actingAs($manager);
        $response = $this->getJson('/api/time-entries/stats/team?date_from=2026-09-14&date_to=2026-09-14')->assertOk();
        $item = collect($response->json('data.items'))->firstWhere('user.id', $member->id);
        expect($item['manager_ids'])->toBe([$lowId, $highId]);
    }
});

it('AC-020: a viewAll holder sees every active user including themselves, is_full_list true', function () {
    $admin = timeEntryActorWith(['viewAny', 'viewAll']);
    $peer = User::factory()->create();
    $inactive = User::factory()->inactive()->create();

    Sanctum::actingAs($admin);
    $response = $this->getJson('/api/time-entries/stats/team?date_from=2026-09-14&date_to=2026-09-14')->assertOk();

    expect($response->json('data.is_full_list'))->toBeTrue();
    $ids = collect($response->json('data.items'))->pluck('user.id');
    expect($ids)->toContain($admin->id)
        ->toContain($peer->id)
        ->not->toContain($inactive->id);
});

it('AC-020: a user with no sottoposti and no viewAll is 403', function () {
    $actor = timeEntryActorWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/time-entries/stats/team')->assertForbidden();
});

it('without time-entries.viewAny, stats/team is 403', function () {
    Permission::findOrCreate('time-entries.viewAny');
    $actor = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/time-entries/stats/team')->assertForbidden();
});

it('a cycle in the reporting hierarchy does not loop and excludes the actor', function () {
    $manager = timeEntryActorWith(['viewAny']);
    $direct = User::factory()->create();
    $indirect = User::factory()->create();
    // A -> B -> C -> A: a 3-cycle including the actor itself.
    EmploymentProfile::factory()->reportsTo($indirect)->create(['user_id' => $manager->id]);
    EmploymentProfile::factory()->reportsTo($manager)->create(['user_id' => $direct->id]);
    EmploymentProfile::factory()->reportsTo($direct)->create(['user_id' => $indirect->id]);

    Sanctum::actingAs($manager);
    $response = $this->getJson('/api/time-entries/stats/team?date_from=2026-09-14&date_to=2026-09-14')->assertOk();

    $ids = collect($response->json('data.items'))->pluck('user.id');
    expect($ids)->toHaveCount(2)
        ->and($ids)->not->toContain($manager->id)
        ->and($ids)->toContain($direct->id, $indirect->id);
});

it('runs a bounded, constant number of queries regardless of team size (no N+1)', function () {
    $manager = timeEntryActorWith(['viewAny']);
    EmploymentProfile::factory()->manager()->create(['user_id' => $manager->id]);
    $typeA = TaskType::factory()->create();

    $smallTeam = User::factory()->count(2)->create();
    foreach ($smallTeam as $member) {
        EmploymentProfile::factory()->reportsTo($manager)->create(['user_id' => $member->id]);
        TimeEntry::factory()->forUser($member)->onDate('2026-09-14')->create(['task_type_id' => $typeA->id, 'minutes' => 60]);
    }

    Sanctum::actingAs($manager);
    DB::enableQueryLog();
    $this->getJson('/api/time-entries/stats/team?date_from=2026-09-14&date_to=2026-09-14')->assertOk()
        ->assertJsonCount(2, 'data.items');
    $smallCount = count(DB::getQueryLog());
    DB::disableQueryLog();
    DB::flushQueryLog();

    $largeManager = timeEntryActorWith(['viewAny']);
    EmploymentProfile::factory()->manager()->create(['user_id' => $largeManager->id]);
    $largeTeam = User::factory()->count(6)->create();
    foreach ($largeTeam as $member) {
        EmploymentProfile::factory()->reportsTo($largeManager)->create(['user_id' => $member->id]);
        TimeEntry::factory()->forUser($member)->onDate('2026-09-14')->create(['task_type_id' => $typeA->id, 'minutes' => 60]);
    }

    Sanctum::actingAs($largeManager);
    DB::enableQueryLog();
    $this->getJson('/api/time-entries/stats/team?date_from=2026-09-14&date_to=2026-09-14')->assertOk()
        ->assertJsonCount(6, 'data.items');
    $largeCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($largeCount)->toBe($smallCount);
});
