<?php

use App\Models\RewardStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('rewardStatusUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function rewardStatusUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("reward-statuses.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("reward-statuses.{$ability}");
        }

        return $user;
    }
}

/**
 * AC-015/BR-7: this is the ONLY test that proves LogsModelActivity,
 * config/activity-log.php ('reward-statuses' => RewardStatus::class) and the
 * morph map ('reward_status' => RewardStatus::class in AppServiceProvider)
 * are ALL THREE wired correctly. Without the morph map,
 * activity_log.subject_type stores the FQCN instead of the map key and the
 * aggregated lookup silently returns nothing instead of 200+events.
 */
it('create + update produce created/updated activity-log events with the changed fields (AC-015, BR-7)', function () {
    $actor = rewardStatusUserWith(['create', 'update', 'view', 'viewActivity']);
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/reward-statuses', ['name' => 'Approvato', 'color' => 'green', 'group' => 'open'])
        ->assertCreated();
    $id = $created->json('data.id');

    $this->patchJson("/api/reward-statuses/{$id}", ['name' => 'Approvato Plus', 'color' => 'blue'])
        ->assertOk();

    $response = $this->getJson("/api/activity-log/reward-statuses/{$id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['success', 'message', 'data' => ['items', 'next_cursor']]);

    $items = collect($response->json('data.items'));

    $createdEvent = $items->firstWhere('event', 'created');
    $updatedEvent = $items->firstWhere('event', 'updated');

    expect($createdEvent)->not->toBeNull()
        ->and($updatedEvent)->not->toBeNull();

    $updatedFields = collect($updatedEvent['changes'])->pluck('field')->all();
    expect($updatedFields)->toContain('name', 'color');

    $byField = collect($updatedEvent['changes'])->keyBy('field');
    expect($byField['name']['old_value'])->toBe('Approvato')
        ->and($byField['name']['new_value'])->toBe('Approvato Plus')
        ->and($byField['color']['old_value'])->toBe('green')
        ->and($byField['color']['new_value'])->toBe('blue');
});

it('403 without reward-statuses.viewActivity (AC-015)', function () {
    $actor = rewardStatusUserWith(['view']);
    $target = RewardStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/activity-log/reward-statuses/{$target->id}")->assertForbidden();
});

it('403 with reward-statuses.viewActivity but without reward-statuses.view on the record (AC-015)', function () {
    $actor = rewardStatusUserWith(['viewActivity']);
    $target = RewardStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/activity-log/reward-statuses/{$target->id}")->assertForbidden();
});
