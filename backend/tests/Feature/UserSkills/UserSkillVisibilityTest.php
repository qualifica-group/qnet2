<?php

use App\Models\User;
use App\Models\UserSkill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function userSkillGridIds(): array
{
    return collect(test()->postJson('/api/tables/user-skills/rows', ['startRow' => 0, 'endRow' => 50])
        ->assertOk()->json('items'))->pluck('id')->sort()->values()->all();
}

/** @return array<string, mixed> */
function userSkillWorld(array $abilities, bool $withTeam = true): array
{
    $actor = skillUserWith($abilities);
    $report = User::factory()->create();
    $gone = User::factory()->create(['is_active' => false]);
    skillProfile($report, $withTeam ? $actor : null);
    skillProfile($gone, $withTeam ? $actor : null);

    return [
        'actor' => $actor,
        'own' => UserSkill::factory()->forUser($actor)->create(),
        'team' => UserSkill::factory()->forUser($report)->create(),
        'stranger' => UserSkill::factory()->create(),
        'inactive' => UserSkill::factory()->forUser($gone)->create(),
    ];
}

it('0286 AC-003: viewAny sees only the own skills, grid and detail', function () {
    $world = userSkillWorld(['viewAny'], withTeam: false);
    Sanctum::actingAs($world['actor']);

    expect(userSkillGridIds())->toBe([$world['own']->id]);
    $this->getJson("/api/user-skills/{$world['own']->id}")->assertOk()->assertJsonPath('data.can_edit', false);
    $this->getJson("/api/user-skills/{$world['team']->id}")->assertNotFound();
    $this->getJson("/api/user-skills/{$world['stranger']->id}")->assertNotFound();
});

// REQUIREMENT CHANGED (spec 0309 D-2/D-3): the team part derives from the org chart
// (no viewTeam permission) and the manager only reads: writing stays with manage.
it('0309 AC-005: a user with no permission but an active subordinate reads self and the descendants, read only', function () {
    $world = userSkillWorld([]);
    Sanctum::actingAs($world['actor']);

    expect(userSkillGridIds())->toBe(collect([$world['own']->id, $world['team']->id])->sort()->values()->all());
    $this->getJson("/api/user-skills/{$world['team']->id}")->assertOk()->assertJsonPath('data.can_edit', false);
    $this->getJson("/api/user-skills/{$world['inactive']->id}")->assertNotFound();
    $this->getJson("/api/user-skills/{$world['stranger']->id}")->assertNotFound();
});

it('0309 AC-005: viewAny plus a subordinate sees self and the team', function () {
    $world = userSkillWorld(['viewAny']);
    Sanctum::actingAs($world['actor']);

    expect(userSkillGridIds())->toBe(collect([$world['own']->id, $world['team']->id])->sort()->values()->all());
    $this->getJson("/api/user-skills/{$world['stranger']->id}")->assertNotFound();
});

it('0286 AC-003: viewAll reads everyone without writing; manage sees and writes everyone', function () {
    $world = userSkillWorld(['viewAll']);
    Sanctum::actingAs($world['actor']);
    expect(userSkillGridIds())->toHaveCount(4);
    $this->getJson("/api/user-skills/{$world['stranger']->id}")->assertOk()->assertJsonPath('data.can_edit', false);
    $this->patchJson("/api/user-skills/{$world['stranger']->id}", ['level' => 4])->assertForbidden();

    Sanctum::actingAs(skillUserWith(['manage']));
    expect(userSkillGridIds())->toHaveCount(4);
    $this->getJson("/api/user-skills/{$world['stranger']->id}")->assertOk()->assertJsonPath('data.can_edit', true);
});

it('0286 AC-003: without any user-skills permission the grid, the detail and the coverage are 403', function () {
    $stranger = UserSkill::factory()->create();
    Sanctum::actingAs(skillUserWith([]));

    $this->postJson('/api/tables/user-skills/rows', ['startRow' => 0, 'endRow' => 10])->assertForbidden();
    $this->getJson("/api/user-skills/{$stranger->id}")->assertForbidden();
    $this->getJson('/api/user-skills/coverage')->assertForbidden();
});

it('0309 AC-005: only deactivated subordinates do not open the module', function () {
    $actor = skillUserWith([]);
    skillProfile(User::factory()->create(['is_active' => false]), $actor);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/user-skills/rows', ['startRow' => 0, 'endRow' => 10])->assertForbidden();
});

it('0286 AC-003: the grid row carries the contract columns, sorts and filters inside the visibility', function () {
    $world = userSkillWorld([]);
    $world['team']->user->update(['name' => 'Zeta Report']);
    $world['team']->skill->update(['category' => 'Backend']);
    Sanctum::actingAs($world['actor']);

    $columns = collect($this->getJson('/api/tables/user-skills/columns')->assertOk()->json('data.columns'))->pluck('id')->all();
    $rows = $this->postJson('/api/tables/user-skills/rows', ['startRow' => 0, 'endRow' => 10, 'sortModel' => [['colId' => 'user_name', 'sort' => 'desc']]])
        ->assertOk()->json('items');
    $byCategory = $this->postJson('/api/tables/user-skills/rows', [
        'startRow' => 0, 'endRow' => 10,
        'filterModel' => ['category' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'Backend']],
    ])->assertOk()->json('items');
    $strangerFiltered = $this->postJson('/api/tables/user-skills/rows', [
        'startRow' => 0, 'endRow' => 10,
        'filterModel' => ['user_name' => ['filterType' => 'text', 'type' => 'contains', 'filter' => $world['stranger']->user->name]],
    ])->assertOk()->json('items');

    expect($columns)->toContain('user_name', 'skill_name', 'category', 'level', 'assessed_on')
        ->and($rows[0])->toHaveKeys(['id', 'user_name', 'skill_name', 'category', 'level', 'assessed_on'])
        ->and($rows[0]['user_name'])->toBe('Zeta Report')
        ->and($rows[0]['level'])->toBeInt()
        ->and(array_column($byCategory, 'id'))->toBe([$world['team']->id])
        ->and($strangerFiltered)->toBe([]);
});
