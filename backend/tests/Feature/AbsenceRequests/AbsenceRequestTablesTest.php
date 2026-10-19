<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function absenceGridIds(string $domain): array
{
    return collect(test()->postJson("/api/tables/{$domain}/rows", ['startRow' => 0, 'endRow' => 50])
        ->assertOk()->json('items'))->pluck('id')->sort()->values()->all();
}

it('AC-010: "mine" lists only the actor requests', function () {
    $actor = absenceUserWith();
    $mine = absenceSubmitted($actor, absenceUserWith());
    absenceSubmitted(absenceUserWith(), absenceUserWith());
    Sanctum::actingAs($actor);

    expect(absenceGridIds('absence-requests'))->toBe([$mine->id]);
});

it('AC-010: "to approve" lists only submitted requests where the actor is an approver', function () {
    $actor = absenceUserWith();
    $toDecide = absenceSubmitted(absenceUserWith(), $actor);
    absenceSubmitted(absenceUserWith(), $actor, ['status' => 'approved']);
    absenceSubmitted(absenceUserWith(), absenceUserWith());
    Sanctum::actingAs($actor);

    expect(absenceGridIds('absence-requests-to-approve'))->toBe([$toDecide->id]);
});

it('AC-010: "all" lists the descendants and the own requests, everything for manageAll', function () {
    $actor = absenceUserWith();
    $subordinate = absenceUserWith();
    absenceProfile($subordinate, $actor);
    $mine = absenceSubmitted($actor, absenceUserWith());
    $team = absenceSubmitted($subordinate, absenceUserWith());
    $stranger = absenceSubmitted(absenceUserWith(), absenceUserWith());

    Sanctum::actingAs($actor);
    expect(absenceGridIds('absence-requests-all'))->toBe(collect([$mine->id, $team->id])->sort()->values()->all());

    Sanctum::actingAs(absenceUserWith(['viewAny', 'manageAll']));
    expect(absenceGridIds('absence-requests-all'))->toHaveCount(3)->toContain($stranger->id);
});

it('AC-010: an approver who is not a superior does not see the request in "all"', function () {
    $actor = absenceUserWith();
    absenceSubmitted(absenceUserWith(), $actor);
    Sanctum::actingAs($actor);

    expect(absenceGridIds('absence-requests-all'))->toBe([]);
});

it('AC-010: the three grids answer 403 without viewAny', function (string $domain) {
    Sanctum::actingAs(absenceUserWith(['create']));

    $this->postJson("/api/tables/{$domain}/rows", ['startRow' => 0, 'endRow' => 50])->assertForbidden();
})->with(['absence-requests', 'absence-requests-to-approve', 'absence-requests-all']);

it('AC-010: rows carry the contract columns, and relation columns sort and filter', function () {
    $actor = absenceUserWith();
    $request = absenceSubmitted($actor, absenceUserWith());
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/absence-requests/rows', [
        'startRow' => 0, 'endRow' => 50,
        'sortModel' => [['colId' => 'absence_type_name', 'sort' => 'asc']],
        'filterModel' => ['user_name' => ['filterType' => 'text', 'type' => 'contains', 'filter' => $actor->name]],
    ])->assertOk()
        ->assertJsonPath('items.0.id', $request->id)
        ->assertJsonPath('items.0.user_name', $actor->name)
        ->assertJsonPath('items.0.status', 'submitted')
        ->assertJsonPath('items.0.decided_by_name', null);
});
