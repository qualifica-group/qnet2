<?php

use App\Models\BusinessFunction;
use App\Models\JobRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * An active user already member of a business function, so the mandatory membership rule (spec 0275 D-4)
 * never interferes with the job-role assertions below.
 */
function jobRolesTarget(): User
{
    return User::factory()->withEmployment(fn ($f) => $f->memberOf(BusinessFunction::factory()->create()))->create();
}

it('0271 AC-008: saving employment.job_role_ids returns the roles; absent key leaves them; [] clears them', function () {
    Sanctum::actingAs(userWithUserAbilities(['update']));
    $target = jobRolesTarget();
    $a = JobRole::factory()->create(['name' => 'Alpha']);
    $b = JobRole::factory()->create(['name' => 'Beta']);

    $response = $this->patchJson("/api/users/{$target->id}", ['employment' => ['job_role_ids' => [$b->id, $a->id]]])
        ->assertOk()
        ->assertJsonMissingPath('data.employment.job_description');

    expect($response->json('data.employment.job_role_ids'))->toEqualCanonicalizing([$a->id, $b->id])
        ->and(collect($response->json('data.employment.job_roles'))->pluck('name')->sort()->values()->all())->toBe(['Alpha', 'Beta']);

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['standard_daily_minutes' => 300]])
        ->assertOk()
        ->assertJsonCount(2, 'data.employment.job_roles');

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['job_role_ids' => []]])
        ->assertOk()
        ->assertJsonPath('data.employment.job_role_ids', [])
        ->assertJsonPath('data.employment.job_roles', []);

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['job_role_ids' => [$a->id]]])->assertOk();
    $this->patchJson("/api/users/{$target->id}", ['employment' => ['job_role_ids' => null]])
        ->assertOk()
        ->assertJsonPath('data.employment.job_role_ids', []);
});

it('0271 AC-008: job_role_ids rejects unknown ids and duplicates with 422', function () {
    Sanctum::actingAs(userWithUserAbilities(['update']));
    $target = jobRolesTarget();
    $role = JobRole::factory()->create();

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['job_role_ids' => [999999]]])
        ->assertStatus(422)->assertJsonValidationErrors(['employment.job_role_ids.0']);

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['job_role_ids' => [$role->id, $role->id]]])
        ->assertStatus(422)->assertJsonValidationErrors(['employment.job_role_ids.0']);
});

it('0271 AC-008: a user without users.update cannot write job roles (403)', function () {
    Sanctum::actingAs(userWithUserAbilities(['view']));
    $target = jobRolesTarget();

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['job_role_ids' => []]])->assertForbidden();
});

it('0271 AC-012: the users grid exposes job_roles (comma-joined, alphabetical) with a set filter and no job_description', function () {
    Sanctum::actingAs(userWithUserAbilities(['viewAny']));
    $withRoles = User::factory()->withEmployment(fn ($f) => $f->memberOf(BusinessFunction::factory()->create()))->create(['name' => 'With Roles']);
    $without = User::factory()->withEmployment(fn ($f) => $f->memberOf(BusinessFunction::factory()->create()))->create(['name' => 'Without Roles']);
    $withRoles->employment->jobRoles()->attach([
        JobRole::factory()->create(['name' => 'Zeta'])->id,
        JobRole::factory()->create(['name' => 'Alpha'])->id,
    ]);

    $meta = $this->getJson('/api/tables/users/columns')->assertOk();
    $columnIds = collect($meta->json('data.columns'))->pluck('id');
    expect($columnIds)->toContain('job_roles')->and($columnIds)->not->toContain('job_description');

    $rows = fn (array $params = []) => collect(
        $this->postJson('/api/tables/users/rows', array_merge(['startRow' => 0, 'endRow' => 50], $params))->assertOk()->json('items')
    )->keyBy('id');

    expect($rows()[$withRoles->id]['job_roles'])->toBe('Alpha, Zeta')
        ->and($rows()[$without->id]['job_roles'])->toBeNull();

    $filtered = $rows(['filterModel' => ['job_roles' => ['filterType' => 'set', 'values' => ['Zeta']]]]);
    expect($filtered->keys()->all())->toContain($withRoles->id)->and($filtered->keys()->all())->not->toContain($without->id);

    $sorted = $rows(['sortModel' => [['colId' => 'job_roles', 'sort' => 'asc']]]);
    expect($sorted->keys()->all())->toContain($withRoles->id);
});
