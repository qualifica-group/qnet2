<?php

use App\Models\JobRole;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserSkill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('0286 AC-002: every endpoint answers 403 without the permission', function () {
    Sanctum::actingAs(hrCatalogUserWith('skills', []));
    $skill = Skill::factory()->create();

    $this->getJson("/api/skills/{$skill->id}")->assertForbidden();
    $this->postJson('/api/skills', ['name' => 'PHP'])->assertForbidden();
    $this->patchJson("/api/skills/{$skill->id}", ['name' => 'X'])->assertForbidden();
    $this->deleteJson("/api/skills/{$skill->id}")->assertForbidden();
    $this->postJson('/api/tables/skills/rows', ['startRow' => 0, 'endRow' => 10])->assertForbidden();
});

it('0286 AC-002: create answers 201 with the job roles and their minimum level', function () {
    Sanctum::actingAs(hrCatalogUserWith('skills', ['create']));
    $role = JobRole::factory()->create(['name' => 'Developer']);

    $this->postJson('/api/skills', ['name' => 'PHP', 'category' => 'Backend', 'job_roles' => [['id' => $role->id, 'min_level' => 3]]])
        ->assertCreated()
        ->assertJsonPath('data.name', 'PHP')
        ->assertJsonPath('data.category', 'Backend')
        ->assertJsonPath('data.job_roles.0', ['id' => $role->id, 'name' => 'Developer', 'min_level' => 3])
        ->assertJsonPath('data.users_count', 0)
        ->assertJsonStructure(['success', 'message', 'data' => ['id', 'description', 'is_active', 'created_at', 'updated_at'], 'permissions']);
});

it('0286 AC-002: patch replaces the roles only when sent and can change a minimum level', function () {
    Sanctum::actingAs(hrCatalogUserWith('skills', ['view', 'update']));
    $role = JobRole::factory()->create();
    $skill = Skill::factory()->create();
    $skill->jobRoles()->attach($role->id, ['min_level' => 1]);

    $this->patchJson("/api/skills/{$skill->id}", ['is_active' => false])
        ->assertOk()->assertJsonPath('data.is_active', false)->assertJsonPath('data.job_roles.0.min_level', 1);
    $this->patchJson("/api/skills/{$skill->id}", ['job_roles' => [['id' => $role->id, 'min_level' => 4]]])
        ->assertOk()->assertJsonPath('data.job_roles.0.min_level', 4);
    $this->patchJson("/api/skills/{$skill->id}", ['job_roles' => []])->assertOk()->assertJsonPath('data.job_roles', []);
    $this->getJson("/api/skills/{$skill->id}")->assertOk()->assertJsonStructure(['permissions']);
});

it('0286 AC-002: validation rejects a duplicated name, a level out of 1..4 and an unknown role', function () {
    Sanctum::actingAs(hrCatalogUserWith('skills', ['create', 'update']));
    Skill::factory()->create(['name' => 'PHP']);
    $role = JobRole::factory()->create();

    $this->postJson('/api/skills', ['name' => 'PHP'])->assertStatus(422)->assertJsonValidationErrors('name');
    foreach ([0, 5] as $level) {
        $this->postJson('/api/skills', ['name' => 'Nuova', 'job_roles' => [['id' => $role->id, 'min_level' => $level]]])
            ->assertStatus(422)->assertJsonValidationErrors('job_roles.0.min_level');
    }
    $this->postJson('/api/skills', ['name' => 'Nuova', 'category' => str_repeat('x', 101), 'job_roles' => [['id' => 9999, 'min_level' => 1]]])
        ->assertStatus(422)->assertJsonValidationErrors(['category', 'job_roles.0.id']);
    $this->postJson('/api/skills', ['name' => 'Nuova', 'job_roles' => [['id' => $role->id]]])
        ->assertStatus(422)->assertJsonValidationErrors('job_roles.0.min_level');
});

it('0286 AC-002: deleting an assigned skill is 422 with the translated message, a free one 204', function () {
    Sanctum::actingAs(hrCatalogUserWith('skills', ['delete']));
    $used = Skill::factory()->create();
    UserSkill::factory()->create(['skill_id' => $used->id]);
    $free = Skill::factory()->create();

    $this->deleteJson("/api/skills/{$used->id}")->assertStatus(422)->assertJsonPath('message', __('skills.in_use'));
    $this->deleteJson("/api/skills/{$free->id}")->assertNoContent();

    expect(Skill::find($used->id))->not->toBeNull()->and(Skill::find($free->id))->toBeNull();
});

it('0286 AC-002: for-select lists active skills with their category, hydrating requested inactive ids', function () {
    Skill::factory()->create(['name' => 'Zeta', 'category' => 'Soft']);
    Skill::factory()->create(['name' => 'Alfa']);
    $inactive = Skill::factory()->inactive()->create(['name' => 'Spenta']);

    $this->getJson('/api/skills/for-select')->assertUnauthorized();

    Sanctum::actingAs(User::factory()->create());
    $plain = $this->getJson('/api/skills/for-select')->assertOk();
    $hydrated = $this->getJson('/api/skills/for-select?ids[]='.$inactive->id)->assertOk();

    expect(collect($plain->json('items'))->pluck('label')->all())->toBe(['Alfa', 'Zeta'])
        ->and($plain->json('items.0'))->toBe(['id' => $plain->json('items.0.id'), 'label' => 'Alfa', 'meta' => ['category' => null]])
        ->and($plain->json('items.1.meta.category'))->toBe('Soft')
        ->and(collect($hydrated->json('items'))->pluck('label')->all())->toBe(['Alfa', 'Zeta', 'Spenta'])
        ->and($hydrated->json('pagination.total'))->toBe(2);
});

it('0286 AC-002: the grid lists name, category, roles, active and the people count', function () {
    Sanctum::actingAs(hrCatalogUserWith('skills', ['viewAny', 'view', 'delete']));
    $role = JobRole::factory()->create(['name' => 'Tecnico']);
    $skill = Skill::factory()->create(['name' => 'Saldatura', 'category' => 'Officina']);
    $skill->jobRoles()->attach($role->id, ['min_level' => 2]);
    UserSkill::factory()->count(2)->create(['skill_id' => $skill->id]);

    $columns = collect($this->getJson('/api/tables/skills/columns')->assertOk()->json('data.columns'))->pluck('id')->all();
    $row = $this->postJson('/api/tables/skills/rows', ['startRow' => 0, 'endRow' => 10])->assertOk()->json('items.0');

    expect($columns)->toContain('name', 'category', 'job_roles', 'is_active', 'users_count')
        ->and($row['job_roles'])->toBe(['Tecnico'])
        ->and($row['category'])->toBe('Officina')
        ->and($row['users_count'])->toBe(2);
    $this->getJson('/api/meta/skills')->assertOk();
});

it('0286 AC-005: skill changes are in the activity log', function () {
    $skill = Skill::factory()->create(['name' => 'Log']);
    $skill->update(['name' => 'Log 2']);

    expect($skill->activities()->count())->toBe(2);
});
