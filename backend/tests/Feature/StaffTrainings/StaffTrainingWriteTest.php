<?php

use App\Authorization\AssignablePermissionCatalogue;
use App\Models\StaffCourse;
use App\Models\StaffTraining;
use App\Models\User;
use App\Policies\StaffTrainingPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('0284 AC-002: store needs manage, answers 201 with the contract shape and records the actor', function () {
    $hr = trainingUserWith(['manage']);
    $person = User::factory()->create();
    $course = StaffCourse::factory()->validFor(12)->create(['name' => 'Privacy']);
    Sanctum::actingAs($hr);

    $this->postJson('/api/staff-trainings', trainingPayload($person, ['staff_course_id' => $course->id, 'completed_on' => '2026-01-31', 'notes' => 'ok']))
        ->assertCreated()
        ->assertJsonPath('data.user.id', $person->id)
        ->assertJsonPath('data.course.name', 'Privacy')
        ->assertJsonPath('data.course.validity_months', 12)
        ->assertJsonPath('data.completed_on', '2026-01-31')
        ->assertJsonPath('data.expires_on', '2027-01-31')
        ->assertJsonPath('data.outcome', 'passed')
        ->assertJsonPath('data.notes', 'ok')
        ->assertJsonPath('data.attachment', null)
        ->assertJsonPath('data.recorded_by.id', $hr->id);
});

it('0284 AC-002: store is 403 without manage, even for who sees everything', function () {
    $person = User::factory()->create();

    foreach ([['viewAny'], ['viewTeam'], ['viewAll'], []] as $abilities) {
        Sanctum::actingAs(trainingUserWith($abilities));
        $this->postJson('/api/staff-trainings', trainingPayload($person))->assertForbidden();
        $this->postJson('/api/staff-trainings', [])->assertForbidden();
    }

    expect(StaffTraining::query()->count())->toBe(0);
});

it('0284 AC-002: validation rejects a future date, a bad outcome and unknown ids', function () {
    Sanctum::actingAs(trainingUserWith(['manage']));
    $person = User::factory()->create();

    $this->postJson('/api/staff-trainings', [])->assertStatus(422)
        ->assertJsonValidationErrors(['user_id', 'staff_course_id', 'completed_on', 'outcome']);
    $this->postJson('/api/staff-trainings', trainingPayload($person, ['completed_on' => now()->addDay()->format('Y-m-d'), 'outcome' => 'maybe', 'notes' => str_repeat('x', 2001)]))
        ->assertStatus(422)->assertJsonValidationErrors(['completed_on', 'outcome', 'notes']);
    $this->postJson('/api/staff-trainings', trainingPayload($person, ['user_id' => 9999, 'staff_course_id' => 9999]))
        ->assertStatus(422)->assertJsonValidationErrors(['user_id', 'staff_course_id']);
});

it('0284 AC-002: expires_on is empty when the course never expires', function () {
    Sanctum::actingAs(trainingUserWith(['manage']));

    $this->postJson('/api/staff-trainings', trainingPayload(User::factory()->create()))
        ->assertCreated()->assertJsonPath('data.expires_on', null);
});

it('0284 AC-002: changing the course validity leaves the recorded trainings alone', function () {
    Sanctum::actingAs(trainingUserWith(['manage']));
    $course = StaffCourse::factory()->validFor(12)->create();
    $id = $this->postJson('/api/staff-trainings', trainingPayload(User::factory()->create(), ['staff_course_id' => $course->id, 'completed_on' => '2026-02-10']))
        ->assertCreated()->json('data.id');

    $course->update(['validity_months' => 36]);

    expect(StaffTraining::find($id)->expires_on->format('Y-m-d'))->toBe('2027-02-10');
});

it('0284 AC-002: update recomputes the expiry when course or date change, not otherwise', function () {
    Sanctum::actingAs(trainingUserWith(['manage']));
    $course = StaffCourse::factory()->validFor(12)->create();
    $longer = StaffCourse::factory()->validFor(24)->create();
    $training = StaffTraining::factory()->create(['staff_course_id' => $course->id, 'completed_on' => '2026-02-10', 'expires_on' => '2027-02-10']);
    $course->update(['validity_months' => 6]);

    $this->patchJson("/api/staff-trainings/{$training->id}", ['notes' => 'rev', 'outcome' => 'failed'])
        ->assertOk()->assertJsonPath('data.expires_on', '2027-02-10')->assertJsonPath('data.outcome', 'failed');
    $this->patchJson("/api/staff-trainings/{$training->id}", ['completed_on' => '2026-03-01'])
        ->assertOk()->assertJsonPath('data.expires_on', '2026-09-01');
    $this->patchJson("/api/staff-trainings/{$training->id}", ['staff_course_id' => $longer->id])
        ->assertOk()->assertJsonPath('data.expires_on', '2028-03-01');
});

it('0284 AC-002: update and destroy are 403 for who sees the record without manage, 204 with it', function () {
    $person = trainingUserWith(['viewAny']);
    $training = StaffTraining::factory()->forUser($person)->create();
    Sanctum::actingAs($person);

    $this->patchJson("/api/staff-trainings/{$training->id}", ['notes' => 'x'])->assertForbidden();
    $this->deleteJson("/api/staff-trainings/{$training->id}")->assertForbidden();

    Sanctum::actingAs(trainingUserWith(['manage']));
    $this->deleteJson("/api/staff-trainings/{$training->id}")->assertNoContent();

    expect(StaffTraining::find($training->id))->toBeNull();
});

it('0284 AC-006: the policies declare the permissions, permissions:sync creates them and the Role form offers them', function () {
    expect(StaffTrainingPolicy::abilities())->toBe(['viewAny', 'viewTeam', 'viewAll', 'manage']);

    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::query()->where('name', 'like', 'staff-trainings.%')->pluck('name')->sort()->values()->all())
        ->toBe(['staff-trainings.manage', 'staff-trainings.viewAll', 'staff-trainings.viewAny', 'staff-trainings.viewTeam'])
        ->and(Permission::query()->where('name', 'like', 'staff-courses.%')->pluck('name')->all())
        ->toContain('staff-courses.viewAny', 'staff-courses.create', 'staff-courses.delete');

    $assignable = app(AssignablePermissionCatalogue::class)->names();

    expect($assignable)->toContain('staff-trainings.manage', 'staff-trainings.viewTeam', 'staff-courses.update');
});

it('0284 AC-006: trainings are in the activity log', function () {
    $training = StaffTraining::factory()->create();
    $training->update(['notes' => 'changed']);

    expect($training->activities()->count())->toBe(2);
});

it('0284 BE-1: the menu shows Formazione after Assenze and the courses in Configurazione', function () {
    $this->artisan('permissions:sync')->assertSuccessful();
    $this->artisan('roles:create-super-admin')->assertSuccessful();
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    $routes = visibleRoutes($admin);

    expect($routes)->toContain('/staff-trainings', '/staff-courses')
        ->and(array_search('/staff-trainings', $routes, true))->toBe(array_search('/absences', $routes, true) + 1);
});
