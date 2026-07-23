<?php

use App\Models\RewardType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('rewardTypeUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function rewardTypeUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("reward-types.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("reward-types.{$ability}");
        }

        return $user;
    }
}

/**
 * BR-5/AC-014: this is the ONLY test that proves LogsModelActivity,
 * config/activity-log.php ('reward-types' => RewardType::class) and the
 * morph map ('reward_type' => RewardType::class in AppServiceProvider) are
 * ALL THREE wired correctly. Without the morph map,
 * activity_log.subject_type stores the FQCN instead of the map key and the
 * aggregated lookup silently returns nothing instead of 200+events.
 */
it('create + update produce created/updated activity-log events with the changed fields (AC-014, BR-5)', function () {
    $actor = rewardTypeUserWith(['create', 'update', 'view', 'viewActivity']);
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/reward-types', ['name' => 'Buono Amazon', 'color' => 'green'])
        ->assertCreated();
    $id = $created->json('data.id');

    $this->patchJson("/api/reward-types/{$id}", ['name' => 'Buono Amazon Plus', 'color' => 'blue'])
        ->assertOk();

    $response = $this->getJson("/api/activity-log/reward-types/{$id}")
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
    expect($byField['name']['old_value'])->toBe('Buono Amazon')
        ->and($byField['name']['new_value'])->toBe('Buono Amazon Plus')
        ->and($byField['color']['old_value'])->toBe('green')
        ->and($byField['color']['new_value'])->toBe('blue');
});

it('403 without reward-types.viewActivity (AC-014)', function () {
    $actor = rewardTypeUserWith(['view']);
    $target = RewardType::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/activity-log/reward-types/{$target->id}")->assertForbidden();
});

it('403 with reward-types.viewActivity but without reward-types.view on the record (AC-014)', function () {
    $actor = rewardTypeUserWith(['viewActivity']);
    $target = RewardType::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/activity-log/reward-types/{$target->id}")->assertForbidden();
});
