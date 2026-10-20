<?php

use App\Models\JobRole;
use App\Models\StaffCourse;
use App\Models\StaffTraining;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

const STAFF_COURSE_PAYLOAD = ['name' => 'Sicurezza base', 'duration_minutes' => 240, 'validity_months' => 60, 'is_active' => true];

it('0284 AC-001: every endpoint answers 403 without the permission', function () {
    Sanctum::actingAs(hrCatalogUserWith('staff-courses', []));
    $course = StaffCourse::factory()->create();

    $this->getJson("/api/staff-courses/{$course->id}")->assertForbidden();
    $this->postJson('/api/staff-courses', STAFF_COURSE_PAYLOAD)->assertForbidden();
    $this->patchJson("/api/staff-courses/{$course->id}", ['name' => 'X'])->assertForbidden();
    $this->deleteJson("/api/staff-courses/{$course->id}")->assertForbidden();
    $this->postJson('/api/tables/staff-courses/rows', ['startRow' => 0, 'endRow' => 10])->assertForbidden();
});

it('0284 AC-001: create answers 201 with the linked job roles and the permissions block', function () {
    Sanctum::actingAs(hrCatalogUserWith('staff-courses', ['create']));
    $role = JobRole::factory()->create();

    $this->postJson('/api/staff-courses', [...STAFF_COURSE_PAYLOAD, 'job_role_ids' => [$role->id]])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Sicurezza base')
        ->assertJsonPath('data.validity_months', 60)
        ->assertJsonPath('data.job_roles.0.id', $role->id)
        ->assertJsonPath('data.trainings_count', 0)
        ->assertJsonStructure(['success', 'message', 'data' => ['id', 'description', 'duration_minutes', 'is_active', 'created_at', 'updated_at'], 'permissions']);

    expect(StaffCourse::where('name', 'Sicurezza base')->firstOrFail()->jobRoles()->pluck('job_roles.id')->all())->toBe([$role->id]);
});

it('0284 AC-001: show and patch answer 200, patch replaces the roles only when sent', function () {
    Sanctum::actingAs(hrCatalogUserWith('staff-courses', ['view', 'update']));
    $keep = JobRole::factory()->create();
    $course = StaffCourse::factory()->create(['name' => 'Antincendio']);
    $course->jobRoles()->attach($keep->id);

    $this->getJson("/api/staff-courses/{$course->id}")
        ->assertOk()->assertJsonPath('data.name', 'Antincendio')->assertJsonStructure(['permissions']);
    $this->patchJson("/api/staff-courses/{$course->id}", ['name' => 'Antincendio 2', 'is_active' => false])
        ->assertOk()->assertJsonPath('data.is_active', false)->assertJsonPath('data.job_roles.0.id', $keep->id);
    $this->patchJson("/api/staff-courses/{$course->id}", ['job_role_ids' => []])
        ->assertOk()->assertJsonPath('data.job_roles', []);
});

it('0284 AC-001: validation rejects a duplicated name, a bad validity and an unknown role', function () {
    Sanctum::actingAs(hrCatalogUserWith('staff-courses', ['create', 'update']));
    StaffCourse::factory()->create(['name' => 'Primo soccorso']);
    $other = StaffCourse::factory()->create(['name' => 'Privacy']);

    $this->postJson('/api/staff-courses', ['name' => 'Primo soccorso'])->assertStatus(422)->assertJsonValidationErrors('name');
    $this->postJson('/api/staff-courses', ['name' => 'Nuovo', 'validity_months' => 241, 'duration_minutes' => 0, 'job_role_ids' => [9999]])
        ->assertStatus(422)->assertJsonValidationErrors(['validity_months', 'duration_minutes', 'job_role_ids.0']);
    $this->patchJson("/api/staff-courses/{$other->id}", ['name' => 'Primo soccorso'])->assertStatus(422)->assertJsonValidationErrors('name');
    $this->patchJson("/api/staff-courses/{$other->id}", ['name' => 'Privacy', 'validity_months' => null])->assertOk();
});

it('0284 AC-001: deleting a course with trainings is 422 with the translated message, a free one 204', function () {
    Sanctum::actingAs(hrCatalogUserWith('staff-courses', ['delete']));
    $used = StaffCourse::factory()->create();
    StaffTraining::factory()->create(['staff_course_id' => $used->id]);
    $free = StaffCourse::factory()->create();

    $this->deleteJson("/api/staff-courses/{$used->id}")
        ->assertStatus(422)
        ->assertJsonPath('message', __('staff_courses.in_use'));
    $this->deleteJson("/api/staff-courses/{$free->id}")->assertNoContent();

    expect(StaffCourse::find($used->id))->not->toBeNull()
        ->and(StaffCourse::find($free->id))->toBeNull();
});

it('0284 AC-001: for-select lists only active courses by name, auth only, hydrating requested ids', function () {
    StaffCourse::factory()->create(['name' => 'Zeta']);
    StaffCourse::factory()->create(['name' => 'Alfa']);
    $inactive = StaffCourse::factory()->inactive()->create(['name' => 'Spento']);

    $this->getJson('/api/staff-courses/for-select')->assertUnauthorized();

    Sanctum::actingAs(User::factory()->create());
    $plain = $this->getJson('/api/staff-courses/for-select')->assertOk();
    $hydrated = $this->getJson('/api/staff-courses/for-select?ids[]='.$inactive->id)->assertOk();

    expect(collect($plain->json('items'))->pluck('label')->all())->toBe(['Alfa', 'Zeta'])
        ->and($plain->json('items.0'))->toHaveKeys(['id', 'label'])
        ->and(collect($hydrated->json('items'))->pluck('label')->all())->toBe(['Alfa', 'Zeta', 'Spento'])
        ->and($hydrated->json('pagination.total'))->toBe(2);
});

it('0284 AC-001: the grid lists the rows with roles and the trainings count', function () {
    Sanctum::actingAs(hrCatalogUserWith('staff-courses', ['viewAny', 'view', 'delete']));
    $role = JobRole::factory()->create(['name' => 'Tecnico']);
    $course = StaffCourse::factory()->create(['name' => 'Elettrico', 'duration_minutes' => 450]);
    StaffCourse::factory()->create(['name' => 'Breve', 'duration_minutes' => 90]);
    $course->jobRoles()->attach($role->id);
    StaffTraining::factory()->count(2)->create(['staff_course_id' => $course->id]);

    $columns = collect($this->getJson('/api/tables/staff-courses/columns')->assertOk()->json('data.columns'))->pluck('id')->all();
    $row = $this->postJson('/api/tables/staff-courses/rows', ['startRow' => 0, 'endRow' => 10, 'filterModel' => ['name' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'Elettrico']]])->assertOk()->json('items.0');
    $byHours = $this->postJson('/api/tables/staff-courses/rows', ['startRow' => 0, 'endRow' => 10, 'sortModel' => [['colId' => 'duration_hours', 'sort' => 'desc']]])->assertOk()->json('items');

    expect($columns)->toContain('name', 'duration_hours', 'validity_months', 'job_roles', 'is_active', 'trainings_count')
        ->and($row['job_roles'])->toBe(['Tecnico'])
        ->and($row['trainings_count'])->toBe(2)
        ->and($row['duration_hours'])->toBe(7.5)
        ->and(collect($byHours)->pluck('duration_hours')->all())->toBe([7.5, 1.5]);

    $this->getJson('/api/meta/staff-courses')->assertOk();
});

it('0284 AC-006: course changes are in the activity log', function () {
    $course = StaffCourse::factory()->create(['name' => 'Log']);
    $course->update(['name' => 'Log 2']);

    expect($course->activities()->count())->toBe(2);
});
