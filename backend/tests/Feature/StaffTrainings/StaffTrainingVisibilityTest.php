<?php

use App\Models\StaffTraining;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function trainingGridIds(): array
{
    return collect(test()->postJson('/api/tables/staff-trainings/rows', ['startRow' => 0, 'endRow' => 50])
        ->assertOk()->json('items'))->pluck('id')->sort()->values()->all();
}

/** @return array{actor: User, own: StaffTraining, team: StaffTraining, stranger: StaffTraining, inactive: StaffTraining} */
function trainingWorld(array $abilities, bool $withTeam = true): array
{
    $actor = trainingUserWith($abilities);
    $report = User::factory()->create();
    $gone = User::factory()->create(['is_active' => false]);
    trainingProfile($report, $withTeam ? $actor : null);
    trainingProfile($gone, $withTeam ? $actor : null);

    return [
        'actor' => $actor,
        'own' => StaffTraining::factory()->forUser($actor)->create(),
        'team' => StaffTraining::factory()->forUser($report)->create(),
        'stranger' => StaffTraining::factory()->create(),
        'inactive' => StaffTraining::factory()->forUser($gone)->create(),
    ];
}

it('0284 AC-003: viewAny sees only the own trainings, grid and detail', function () {
    $world = trainingWorld(['viewAny'], withTeam: false);
    Sanctum::actingAs($world['actor']);

    expect(trainingGridIds())->toBe([$world['own']->id]);
    $this->getJson("/api/staff-trainings/{$world['own']->id}")->assertOk();
    $this->getJson("/api/staff-trainings/{$world['team']->id}")->assertNotFound();
    $this->getJson("/api/staff-trainings/{$world['stranger']->id}")->assertNotFound();
});

// REQUIREMENT CHANGED (spec 0309 D-2): the team part derives from the org chart, no viewTeam permission.
it('0309 AC-003: a user with no permission but an active subordinate sees self and the descendants', function () {
    $world = trainingWorld([]);
    Sanctum::actingAs($world['actor']);

    expect(trainingGridIds())->toBe(collect([$world['own']->id, $world['team']->id])->sort()->values()->all());
    $this->getJson("/api/staff-trainings/{$world['team']->id}")->assertOk();
    $this->getJson("/api/staff-trainings/{$world['inactive']->id}")->assertNotFound();
    $this->getJson("/api/staff-trainings/{$world['stranger']->id}")->assertNotFound();
});

it('0309 AC-004: viewAny plus a subordinate sees self and the team', function () {
    $world = trainingWorld(['viewAny']);
    Sanctum::actingAs($world['actor']);

    expect(trainingGridIds())->toBe(collect([$world['own']->id, $world['team']->id])->sort()->values()->all());
    $this->getJson("/api/staff-trainings/{$world['stranger']->id}")->assertNotFound();
});

it('0284 AC-003: viewAll sees everyone and the widest permission wins', function () {
    $world = trainingWorld(['viewAny', 'viewAll']);
    Sanctum::actingAs($world['actor']);

    expect(trainingGridIds())->toHaveCount(4);
    $this->getJson("/api/staff-trainings/{$world['stranger']->id}")->assertOk();
});

it('0284 AC-003: without any staff-trainings permission the grid and the detail are 403', function () {
    $stranger = StaffTraining::factory()->create();
    Sanctum::actingAs(trainingUserWith([]));

    $this->postJson('/api/tables/staff-trainings/rows', ['startRow' => 0, 'endRow' => 10])->assertForbidden();
    $this->getJson("/api/staff-trainings/{$stranger->id}")->assertForbidden();
    $this->getJson('/api/staff-trainings/compliance')->assertForbidden();
});

it('0309 AC-004: only deactivated subordinates do not open the module', function () {
    $actor = trainingUserWith([]);
    trainingProfile(User::factory()->create(['is_active' => false]), $actor);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/staff-trainings/rows', ['startRow' => 0, 'endRow' => 10])->assertForbidden();
});

it('0284 AC-003: an out-of-visibility record is a 404 on write too, never a 403', function () {
    $world = trainingWorld(['viewAny']);
    Sanctum::actingAs($world['actor']);

    $this->patchJson("/api/staff-trainings/{$world['stranger']->id}", ['notes' => 'x'])->assertNotFound();
    $this->deleteJson("/api/staff-trainings/{$world['stranger']->id}")->assertNotFound();
});

it('0284 AC-003: the grid row carries the contract columns, the attachment flag and filters stay in scope', function () {
    $world = trainingWorld([]);
    $world['team']->user->update(['name' => 'Zeta Report']);
    Sanctum::actingAs($world['actor']);

    $columns = collect($this->getJson('/api/tables/staff-trainings/columns')->assertOk()->json('data.columns'))->pluck('id')->all();
    $rows = $this->postJson('/api/tables/staff-trainings/rows', [
        'startRow' => 0, 'endRow' => 10,
        'sortModel' => [['colId' => 'user_name', 'sort' => 'desc']],
    ])->assertOk()->json('items');
    $filtered = $this->postJson('/api/tables/staff-trainings/rows', [
        'startRow' => 0, 'endRow' => 10,
        'filterModel' => ['user_name' => ['filterType' => 'text', 'type' => 'contains', 'filter' => $world['stranger']->user->name]],
    ])->assertOk()->json('items');

    expect($columns)->toContain('user_name', 'course_name', 'completed_on', 'expires_on', 'outcome', 'has_attachment')
        ->and($rows[0])->toHaveKeys(['id', 'user_name', 'course_name', 'completed_on', 'expires_on', 'outcome', 'has_attachment'])
        ->and($rows[0]['user_name'])->toBe('Zeta Report')
        ->and($rows[0]['has_attachment'])->toBeFalse()
        ->and($filtered)->toBe([]);
});

it('0284: the outcome column carries its enum key, so the grid and its filter show translated values', function () {
    Sanctum::actingAs(trainingUserWith(['viewAny']));

    $outcome = collect($this->getJson('/api/tables/staff-trainings/columns')->assertOk()->json('data.columns'))->firstWhere('id', 'outcome');

    expect($outcome['enumKey'] ?? null)->toBe('staff_training_outcome');
});
