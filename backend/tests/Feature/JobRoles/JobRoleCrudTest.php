<?php

use App\Models\EmploymentProfile;
use App\Models\JobActivity;
use App\Models\JobRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-005: create with two activities returns them and the activity lists the role', function () {
    Sanctum::actingAs(hrCatalogUserWith('job-roles', ['create']));
    $activities = JobActivity::factory()->count(2)->create();

    $response = $this->postJson('/api/job-roles', [
        'name' => 'Buyer',
        'job_activity_ids' => $activities->pluck('id')->all(),
    ])->assertCreated()
        ->assertJsonPath('data.users_count', 0);

    expect($response->json('data.job_activity_ids'))->toEqualCanonicalizing($activities->pluck('id')->all())
        ->and(collect($response->json('data.job_activities'))->pluck('name')->all())
        ->toEqualCanonicalizing($activities->pluck('name')->all());

    Sanctum::actingAs(hrCatalogUserWith('job-activities', ['view']));
    $this->getJson("/api/job-activities/{$activities->first()->id}")
        ->assertOk()
        ->assertJsonPath('data.job_roles.0.name', 'Buyer')
        ->assertJsonPath('data.job_role_ids', [$response->json('data.id')]);
});

it('AC-005: duplicated name and unknown activity are 422', function () {
    Sanctum::actingAs(hrCatalogUserWith('job-roles', ['create']));
    JobRole::factory()->create(['name' => 'Buyer']);

    $this->postJson('/api/job-roles', ['name' => 'Buyer'])->assertStatus(422)->assertJsonValidationErrors('name');
    $this->postJson('/api/job-roles', ['name' => 'Seller', 'job_activity_ids' => [999999]])
        ->assertStatus(422)->assertJsonValidationErrors('job_activity_ids.0');
});

it('AC-005: update replaces the activities only when the key is sent', function () {
    Sanctum::actingAs(hrCatalogUserWith('job-roles', ['update']));
    $activity = JobActivity::factory()->create();
    $role = JobRole::factory()->create();
    $role->jobActivities()->attach($activity);

    $this->putJson("/api/job-roles/{$role->id}", ['description' => 'x'])
        ->assertOk()->assertJsonPath('data.job_activity_ids', [$activity->id]);
    $this->putJson("/api/job-roles/{$role->id}", ['job_activity_ids' => null])
        ->assertOk()->assertJsonPath('data.job_activity_ids', []);
});

it('AC-006: a job role assigned to a person is a 409, a free one is deleted', function () {
    Sanctum::actingAs(hrCatalogUserWith('job-roles', ['delete', 'view']));
    $used = JobRole::factory()->create();
    EmploymentProfile::factory()->create()->jobRoles()->attach($used);
    $free = JobRole::factory()->create();

    $this->deleteJson("/api/job-roles/{$used->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', __('job_roles.in_use'));
    $this->deleteJson("/api/job-roles/{$free->id}")->assertNoContent();
    $this->getJson("/api/job-roles/{$used->id}")->assertOk()->assertJsonPath('data.users_count', 1);
});

it('the job-roles grid exposes the contract columns and users_count', function () {
    Sanctum::actingAs(hrCatalogUserWith('job-roles', ['viewAny', 'view']));
    $role = JobRole::factory()->create();
    $activity = JobActivity::factory()->create();
    $role->jobActivities()->attach($activity);
    EmploymentProfile::factory()->create()->jobRoles()->attach($role);

    $columns = collect($this->getJson('/api/tables/job-roles/columns')->assertOk()->json('data.columns'))->pluck('id')->all();
    $row = $this->postJson('/api/tables/job-roles/rows', ['startRow' => 0, 'endRow' => 10])->assertOk()->json('items.0');

    expect($columns)->toBe(['id', 'name', 'description', 'job_activities', 'users_count'])
        ->and($row['job_activities'])->toBe([$activity->name])
        ->and($row['users_count'])->toBe(1);
});

it('for-select returns id/label', function () {
    Sanctum::actingAs(hrCatalogUserWith('job-roles', ['view']));
    JobRole::factory()->create(['name' => 'Buyer']);

    $this->getJson('/api/job-roles/for-select')
        ->assertOk()
        ->assertJsonPath('items.0.label', 'Buyer');
});
