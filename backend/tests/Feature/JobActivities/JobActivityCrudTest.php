<?php

use App\Models\JobActivity;
use App\Models\JobRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-005: create persists the activity with empty roles and abilities', function () {
    Sanctum::actingAs(hrCatalogUserWith('job-activities', ['create']));

    $this->postJson('/api/job-activities', ['name' => 'Invoicing', 'description' => 'Fatturazione'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Invoicing')
        ->assertJsonPath('data.job_role_ids', [])
        ->assertJsonPath('data.abilities.delete', false);

    $this->assertDatabaseHas('job_activities', ['name' => 'Invoicing']);
});

it('AC-005: the role link cannot be written from the activity side', function () {
    Sanctum::actingAs(hrCatalogUserWith('job-activities', ['create', 'update']));
    $role = JobRole::factory()->create();

    $this->postJson('/api/job-activities', ['name' => 'Invoicing', 'job_role_ids' => [$role->id]])
        ->assertCreated()
        ->assertJsonPath('data.job_role_ids', []);
});

it('AC-005: duplicated name is a 422 on create and update, own name is fine', function () {
    Sanctum::actingAs(hrCatalogUserWith('job-activities', ['create', 'update']));
    $other = JobActivity::factory()->create(['name' => 'Filing']);
    JobActivity::factory()->create(['name' => 'Invoicing']);

    $this->postJson('/api/job-activities', ['name' => 'Invoicing'])->assertStatus(422)->assertJsonValidationErrors('name');
    $this->putJson("/api/job-activities/{$other->id}", ['name' => 'Invoicing'])->assertStatus(422)->assertJsonValidationErrors('name');
    $this->putJson("/api/job-activities/{$other->id}", ['name' => 'Filing', 'description' => null])->assertOk();
});

it('AC-006: an activity linked to a job role is a 409, a free one is deleted', function () {
    Sanctum::actingAs(hrCatalogUserWith('job-activities', ['delete']));
    $used = JobActivity::factory()->create();
    JobRole::factory()->create()->jobActivities()->attach($used);
    $free = JobActivity::factory()->create();

    $this->deleteJson("/api/job-activities/{$used->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', __('job_activities.in_use'));
    $this->deleteJson("/api/job-activities/{$free->id}")->assertNoContent();
});

it('the job-activities grid exposes the contract columns', function () {
    Sanctum::actingAs(hrCatalogUserWith('job-activities', ['viewAny', 'view']));
    $activity = JobActivity::factory()->create();
    $role = JobRole::factory()->create();
    $role->jobActivities()->attach($activity);

    $columns = collect($this->getJson('/api/tables/job-activities/columns')->assertOk()->json('data.columns'))->pluck('id')->all();
    $row = $this->postJson('/api/tables/job-activities/rows', ['startRow' => 0, 'endRow' => 10])->assertOk()->json('items.0');

    expect($columns)->toBe(['id', 'name', 'description', 'job_roles'])
        ->and($row['job_roles'])->toBe([$role->name]);
});

it('for-select returns id/label', function () {
    Sanctum::actingAs(hrCatalogUserWith('job-activities', ['view']));
    JobActivity::factory()->create(['name' => 'Invoicing']);

    $this->getJson('/api/job-activities/for-select')
        ->assertOk()
        ->assertJsonPath('items.0.label', 'Invoicing');
});
