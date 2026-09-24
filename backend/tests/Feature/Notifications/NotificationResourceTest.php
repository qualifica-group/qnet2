<?php

use App\Models\User;
use Database\Factories\NotificationFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('emits the guaranteed data shape with all four keys and a valid level', function () {
    $user = User::factory()->create();
    NotificationFactory::new()->forUser($user)->state([
        'data' => ['title' => 'Hi', 'message' => 'There', 'level' => 'success', 'action_url' => 'https://example.test'],
    ])->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/notifications')
        ->assertOk()
        ->assertJsonPath('items.0.data.title', 'Hi')
        ->assertJsonPath('items.0.data.message', 'There')
        ->assertJsonPath('items.0.data.level', 'success')
        ->assertJsonPath('items.0.data.action_url', 'https://example.test');
});

it('normalizes a partial/legacy payload: missing keys become null, unknown level falls back to info', function () {
    $user = User::factory()->create();
    // A row stored before the convention existed: only a message, bogus level.
    NotificationFactory::new()->forUser($user)->state([
        'data' => ['message' => 'Legacy', 'level' => 'bogus'],
    ])->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/notifications')
        ->assertOk()
        ->assertJsonPath('items.0.data.title', null)
        ->assertJsonPath('items.0.data.message', 'Legacy')
        ->assertJsonPath('items.0.data.level', 'info')
        ->assertJsonPath('items.0.data.action_url', null)
        // The four keys are always present even when the stored row was sparse.
        ->assertJsonStructure(['items' => [['data' => ['title', 'message', 'level', 'action_url']]]]);
});

// Spec 0153, D-14: the watchers copied on a task update request get a
// separate notification flagged is_cc; every other row reads false.
it('exposes is_cc, true for a stored copy and false when absent', function () {
    $user = User::factory()->create();
    NotificationFactory::new()->forUser($user)->state(['data' => ['title' => 'Copia', 'is_cc' => true]])->create();
    NotificationFactory::new()->forUser($user)->state(['data' => ['title' => 'Diretta']])->create();
    Sanctum::actingAs($user);

    $items = collect($this->getJson('/api/notifications')->assertOk()->json('items'))->keyBy('data.title');

    expect($items['Copia']['data']['is_cc'])->toBeTrue()
        ->and($items['Diretta']['data']['is_cc'])->toBeFalse();
});
