<?php

use App\Authorization\AssignablePermissionCatalogue;
use App\Enums\SkillLevel;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserSkill;
use App\Policies\UserSkillPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('0286 AC-003: manage assigns a skill to anyone, 201 with the contract shape and the assessor', function () {
    $hr = skillUserWith(['manage']);
    $person = User::factory()->create();
    $skill = Skill::factory()->create(['name' => 'PHP', 'category' => 'Backend']);
    Sanctum::actingAs($hr);

    $this->postJson('/api/user-skills', userSkillPayload($person, ['skill_id' => $skill->id, 'level' => 3, 'notes' => 'ok']))
        ->assertCreated()
        ->assertJsonPath('data.user.id', $person->id)
        ->assertJsonPath('data.skill', ['id' => $skill->id, 'name' => 'PHP', 'category' => 'Backend'])
        ->assertJsonPath('data.level', 3)
        ->assertJsonPath('data.notes', 'ok')
        ->assertJsonPath('data.assessed_by.id', $hr->id)
        ->assertJsonPath('data.can_edit', true);
});

// REQUIREMENT CHANGED (spec 0309 D-3, supersedes spec 0286 D-5): a manager only reads the
// subordinates' skills; writing is user-skills.manage.
it('0309 AC-006: a manager without manage gets 403 on create, update and delete for a subordinate', function () {
    $manager = skillUserWith([]);
    $report = User::factory()->create();
    skillProfile($report, $manager);
    $userSkill = UserSkill::factory()->forUser($report)->create();
    Sanctum::actingAs($manager);

    $this->postJson('/api/user-skills', userSkillPayload($report))->assertForbidden();
    $this->patchJson("/api/user-skills/{$userSkill->id}", ['level' => 1])->assertForbidden();
    $this->deleteJson("/api/user-skills/{$userSkill->id}")->assertForbidden();

    expect(UserSkill::query()->count())->toBe(1);
});

it('0309 AC-006: a manager with manage writes for a subordinate', function () {
    $manager = skillUserWith(['manage']);
    $report = User::factory()->create();
    skillProfile($report, $manager);
    Sanctum::actingAs($manager);

    $this->postJson('/api/user-skills', userSkillPayload($report))->assertCreated()->assertJsonPath('data.can_edit', true);
});

it('0286 AC-003: viewAny and viewAll cannot write, even on themselves', function () {
    foreach ([['viewAny'], ['viewAll'], []] as $abilities) {
        $actor = skillUserWith($abilities);
        Sanctum::actingAs($actor);

        $this->postJson('/api/user-skills', userSkillPayload($actor))->assertForbidden();
        $this->postJson('/api/user-skills', [])->assertForbidden();
    }

    expect(UserSkill::query()->count())->toBe(0);
});

it('0286 AC-003: validation rejects bad level, future date, unknown ids and a long note', function () {
    Sanctum::actingAs(skillUserWith(['manage']));
    $person = User::factory()->create();

    $this->postJson('/api/user-skills', [])->assertStatus(422)->assertJsonValidationErrors(['user_id', 'skill_id', 'level', 'assessed_on']);
    foreach ([0, 5] as $level) {
        $this->postJson('/api/user-skills', userSkillPayload($person, ['level' => $level]))->assertStatus(422)->assertJsonValidationErrors('level');
    }
    $this->postJson('/api/user-skills', userSkillPayload($person, ['assessed_on' => now()->addDay()->format('Y-m-d'), 'notes' => str_repeat('x', 2001)]))
        ->assertStatus(422)->assertJsonValidationErrors(['assessed_on', 'notes']);
    $this->postJson('/api/user-skills', userSkillPayload($person, ['user_id' => 9999, 'skill_id' => 9999]))
        ->assertStatus(422)->assertJsonValidationErrors(['user_id', 'skill_id']);
});

it('0286 AC-003: a second row for the same person and skill is 422, on store and on update', function () {
    Sanctum::actingAs(skillUserWith(['manage']));
    $person = User::factory()->create();
    $other = User::factory()->create();
    $existing = UserSkill::factory()->forUser($person)->create();
    $second = UserSkill::factory()->forUser($person)->create();
    $onOther = UserSkill::factory()->forUser($other)->create(['skill_id' => $existing->skill_id]);

    $this->postJson('/api/user-skills', userSkillPayload($person, ['skill_id' => $existing->skill_id]))
        ->assertStatus(422)->assertJsonValidationErrors('skill_id');
    $this->patchJson("/api/user-skills/{$second->id}", ['skill_id' => $existing->skill_id])->assertStatus(422)->assertJsonValidationErrors('skill_id');
    $this->patchJson("/api/user-skills/{$onOther->id}", ['user_id' => $person->id])->assertStatus(422)->assertJsonValidationErrors('skill_id');
    $this->patchJson("/api/user-skills/{$second->id}", ['level' => 4, 'skill_id' => $second->skill_id])->assertOk();
});

it('0286 AC-003: update changes the level in place, records the assessor and keeps the history in the log', function () {
    $manager = skillUserWith(['manage']);
    $userSkill = UserSkill::factory()->atLevel(SkillLevel::Basic)->create();
    Sanctum::actingAs($manager);

    $this->patchJson("/api/user-skills/{$userSkill->id}", ['level' => 4])
        ->assertOk()->assertJsonPath('data.level', 4)->assertJsonPath('data.assessed_by.id', $manager->id);

    expect(UserSkill::query()->count())->toBe(1)->and($userSkill->activities()->count())->toBe(2);
});

it('0286 AC-003: update and destroy are 403 on a visible record that is not writable, 404 out of visibility', function () {
    $actor = skillUserWith(['viewAny']);
    $own = UserSkill::factory()->forUser($actor)->create();
    $stranger = UserSkill::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/user-skills/{$own->id}", ['level' => 4])->assertForbidden();
    $this->deleteJson("/api/user-skills/{$own->id}")->assertForbidden();
    $this->patchJson("/api/user-skills/{$stranger->id}", ['level' => 4])->assertNotFound();
    $this->deleteJson("/api/user-skills/{$stranger->id}")->assertNotFound();
});

it('0286 AC-005: the policies declare the permissions, permissions:sync creates them and the Role form offers them', function () {
    expect(UserSkillPolicy::abilities())->toBe(['viewAny', 'viewAll', 'manage']);

    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::query()->where('name', 'like', 'user-skills.%')->pluck('name')->sort()->values()->all())
        ->toBe(['user-skills.manage', 'user-skills.viewAll', 'user-skills.viewAny'])
        ->and(Permission::query()->where('name', 'like', 'skills.%')->pluck('name')->all())
        ->toContain('skills.viewAny', 'skills.create', 'skills.delete');

    expect(app(AssignablePermissionCatalogue::class)->names())->toContain('user-skills.manage', 'skills.update');
});

it('0286 BE-1: the menu shows Competenze after Formazione and the catalogue in Configurazione', function () {
    $this->artisan('permissions:sync')->assertSuccessful();
    $this->artisan('roles:create-super-admin')->assertSuccessful();
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    $routes = visibleRoutes($admin);

    expect($routes)->toContain('/user-skills', '/skills')
        ->and(array_search('/user-skills', $routes, true))->toBe(array_search('/staff-trainings', $routes, true) + 1);
});
