<?php

declare(strict_types=1);

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// AC-006: PATCH /api/notifications/{id}/unread — idempotent, 404 on a foreign id.

it('PATCH /api/notifications/{id}/unread: marks a read notification unread (AC-006)', function () {
    $actor = User::factory()->create();
    $notification = Notification::factory()->forUser($actor)->read()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/notifications/{$notification->id}/unread")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.read_at', null);

    expect($notification->fresh()->read_at)->toBeNull();
});

it('PATCH /api/notifications/{id}/unread: idempotent on an already-unread notification (AC-006)', function () {
    $actor = User::factory()->create();
    $notification = Notification::factory()->forUser($actor)->unread()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/notifications/{$notification->id}/unread")
        ->assertOk()
        ->assertJsonPath('data.read_at', null);

    $this->patchJson("/api/notifications/{$notification->id}/unread")
        ->assertOk()
        ->assertJsonPath('data.read_at', null);

    expect($notification->fresh()->read_at)->toBeNull();
});

it('PATCH /api/notifications/{id}/unread: 404 on another user\'s notification, left untouched (AC-006)', function () {
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $notification = Notification::factory()->forUser($owner)->read()->create();
    $readAt = $notification->read_at;
    Sanctum::actingAs($actor);

    $this->patchJson("/api/notifications/{$notification->id}/unread")->assertNotFound();

    expect($notification->fresh()->read_at?->toIso8601String())->toBe($readAt->toIso8601String());
});

it('PATCH /api/notifications/{id}/unread: 401 unauthenticated', function () {
    $notification = Notification::factory()->create();

    $this->patchJson("/api/notifications/{$notification->id}/unread")->assertUnauthorized();
});

// AC-007: POST /api/notifications/bulk-read — marks own unread ids, ignores foreign ids, validates the payload.

it('POST /api/notifications/bulk-read: marks the actor\'s own unread ids, ignores a foreign one (AC-007)', function () {
    $actor = User::factory()->create();
    $other = User::factory()->create();

    $mine = Notification::factory()->forUser($actor)->unread()->count(2)->create();
    $foreign = Notification::factory()->forUser($other)->unread()->create();

    Sanctum::actingAs($actor);

    $this->postJson('/api/notifications/bulk-read', [
        'ids' => [...$mine->pluck('id')->all(), $foreign->id],
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.marked', 2);

    foreach ($mine as $notification) {
        expect($notification->fresh()->read_at)->not->toBeNull();
    }

    expect($foreign->fresh()->read_at)->toBeNull();
});

it('POST /api/notifications/bulk-read: 422 on an empty ids array (AC-007)', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/notifications/bulk-read', ['ids' => []])
        ->assertStatus(422);
});

it('POST /api/notifications/bulk-read: 422 on a non-uuid id (AC-007)', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/notifications/bulk-read', ['ids' => ['not-a-uuid']])
        ->assertStatus(422);
});

it('POST /api/notifications/bulk-read: 422 beyond 500 ids (AC-007)', function () {
    Sanctum::actingAs(User::factory()->create());

    $ids = array_map(static fn (): string => (string) Str::uuid(), range(1, 501));

    $this->postJson('/api/notifications/bulk-read', ['ids' => $ids])
        ->assertStatus(422);
});

it('POST /api/notifications/bulk-read: 401 unauthenticated', function () {
    $this->postJson('/api/notifications/bulk-read', ['ids' => [(string) Str::uuid()]])
        ->assertUnauthorized();
});
