<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Feature coverage for spec 0166 (a user reporting to several managers,
 * replacing the single `reports_to_id` FK with the `employment_profile_manager`
 * pivot). Split out of UserEmploymentTest.php (which already covers spec
 * 0015/0103) purely to stay under the file-size limit — same actor/profile
 * helpers, redeclared `if (! function_exists())` so both files can load in
 * the same Pest run.
 */
if (! function_exists('employmentTestActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function employmentTestActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("users.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("users.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-003 — multiple managers persisted, ordered by name in the response.
// ---------------------------------------------------------------------------

it('0166 AC-003: PUT with two manager ids saves both, ordered by name in the response', function () {
    $actor = employmentTestActor(['update']);
    $managerB = User::factory()->create(['name' => 'Bianchi']);
    $managerA = User::factory()->create(['name' => 'Andreoli']);
    $target = User::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['reports_to_ids' => [$managerB->id, $managerA->id]],
    ])->assertOk();

    $profileId = $target->employment()->first()->id;
    $this->assertDatabaseHas('employment_profile_manager', ['employment_profile_id' => $profileId, 'user_id' => $managerA->id]);
    $this->assertDatabaseHas('employment_profile_manager', ['employment_profile_id' => $profileId, 'user_id' => $managerB->id]);

    // Ordered by manager name (Andreoli before Bianchi), regardless of
    // submission order.
    $response->assertJsonPath('data.employment.reports_to_ids', [$managerA->id, $managerB->id])
        ->assertJsonPath('data.employment.reports_to.0.label', 'Andreoli')
        ->assertJsonPath('data.employment.reports_to.1.label', 'Bianchi');
});

// ---------------------------------------------------------------------------
// AC-004 — tri-state: absent leaves untouched, [] clears.
// ---------------------------------------------------------------------------

it('0166 AC-004: reports_to_ids: [] clears the managers; employment without the key leaves them untouched', function () {
    $actor = employmentTestActor(['update']);
    $manager = User::factory()->create();
    $target = User::factory()->withEmployment(fn ($f) => $f->reportsTo($manager))->create();
    Sanctum::actingAs($actor);

    // Absent key: another employment field changes, managers stay.
    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['job_description' => 'Still reporting'],
    ])->assertOk()->assertJsonPath('data.employment.reports_to_ids', [$manager->id]);

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['reports_to_ids' => []],
    ])->assertOk()->assertJsonPath('data.employment.reports_to_ids', []);

    $profileId = $target->employment()->first()->id;
    $this->assertDatabaseMissing('employment_profile_manager', ['employment_profile_id' => $profileId]);
});

// ---------------------------------------------------------------------------
// AC-006 — duplicate id in the array is a 422, no partial write.
// ---------------------------------------------------------------------------

it('0166 AC-006: duplicate ids in reports_to_ids are rejected (422) with no pivot rows written', function () {
    $actor = employmentTestActor(['update']);
    $target = User::factory()->create();
    $manager = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['reports_to_ids' => [$manager->id, $manager->id]],
    ])->assertStatus(422)->assertJsonValidationErrors([
        'employment.reports_to_ids.0',
        'employment.reports_to_ids.1',
    ]);

    $this->assertDatabaseMissing('employment_profile_manager', ['user_id' => $manager->id]);
});

// ---------------------------------------------------------------------------
// AC-007 — a genuine set change logs one explicit activity entry; a no-op
// resubmit (same set, any order) logs none.
// ---------------------------------------------------------------------------

it('0166 AC-007: a genuine change to the manager set logs one explicit activity entry; resubmitting the same set logs none', function () {
    $actor = employmentTestActor(['update']);
    $managerA = User::factory()->create();
    $managerB = User::factory()->create();
    $target = User::factory()->withEmployment(fn ($f) => $f->reportsTo($managerA))->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['reports_to_ids' => [$managerA->id, $managerB->id]],
    ])->assertOk();

    $profile = $target->employment()->first();
    $activity = Activity::query()
        ->where('subject_type', $profile->getMorphClass())
        ->where('subject_id', $profile->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties->get('attributes'))->toMatchArray(['reports_to_ids' => [$managerA->id, $managerB->id]]);
    expect($activity->properties->get('old'))->toMatchArray(['reports_to_ids' => [$managerA->id]]);

    $countBefore = Activity::query()
        ->where('subject_type', $profile->getMorphClass())
        ->where('subject_id', $profile->id)
        ->count();

    // Same set, reversed order — a no-op, no new log entry.
    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['reports_to_ids' => [$managerB->id, $managerA->id]],
    ])->assertOk();

    $countAfter = Activity::query()
        ->where('subject_type', $profile->getMorphClass())
        ->where('subject_id', $profile->id)
        ->count();

    expect($countAfter)->toBe($countBefore);
});
