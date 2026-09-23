<?php

declare(strict_types=1);

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// Regression (verifier-reported RED): PATCH /api/tables/{domain}/rows/{row}
// typed its {row} route param `int` — a uuid domain (`notifications`) blew
// up with an uncaught TypeError during Laravel's route-action-argument
// binding, BEFORE the controller's own try/catch ever ran: a raw 500 that
// bypassed the {success,message} envelope entirely. `notifications` has no
// editable column at all (D-2) and `authorizeUpdate()` is hardcoded false
// (NotificationsTableDefinition), so the generic engine must now answer
// cleanly — 403 for the actor's own row (found, but never updatable), 404
// for a foreign/unknown one — never a 500, and never write anything.

it('PATCH /api/tables/notifications/rows/{uuid}: 403 with the envelope on the actor\'s own row, no write', function () {
    $actor = User::factory()->create();
    $notification = Notification::factory()->forUser($actor)->unread()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/notifications/rows/{$notification->id}", [
        'column' => 'title',
        'value' => 'Hacked',
    ])
        ->assertForbidden()
        ->assertJsonPath('success', false);

    expect($notification->fresh()->read_at)->toBeNull()
        ->and($notification->fresh()->data['title'] ?? null)->not->toBe('Hacked');
});

it('PATCH /api/tables/notifications/rows/{uuid}: 404 with the envelope on a foreign row, no write', function () {
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $notification = Notification::factory()->forUser($owner)->unread()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/notifications/rows/{$notification->id}", [
        'column' => 'title',
        'value' => 'Hacked',
    ])
        ->assertNotFound()
        ->assertJsonPath('success', false);

    expect($notification->fresh()->data['title'] ?? null)->not->toBe('Hacked');
});

it('PATCH /api/tables/notifications/rows/{uuid}: 404 with the envelope on an unknown uuid, never a 500', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->patchJson('/api/tables/notifications/rows/'.(string) Str::uuid(), [
        'column' => 'title',
        'value' => 'Hacked',
    ])
        ->assertNotFound()
        ->assertJsonPath('success', false);
});
