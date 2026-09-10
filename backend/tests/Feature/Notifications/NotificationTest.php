<?php

use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

if (! function_exists('notify')) {
    /**
     * Send a GenericNotification to the user and return the created row.
     */
    function notify(User $user, string $title = 'Hello', string $message = 'World', string $level = 'info', ?string $url = null): void
    {
        $user->notify(new GenericNotification($title, $message, $level, $url));
    }
}

// ---------------------------------------------------------------------------
// list — GET /api/notifications
// ---------------------------------------------------------------------------

it('list: returns only the actor own notifications, not another user', function () {
    $actor = User::factory()->create();
    $other = User::factory()->create();

    notify($actor, 'Mine');
    notify($other, 'Theirs');

    Sanctum::actingAs($actor);

    $this->getJson('/api/notifications')
        ->assertOk()
        ->assertJsonPath('pagination.total', 1)
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('items.0.data.title', 'Mine');
});

it('list: returns the standard paginated envelope shape', function () {
    $actor = User::factory()->create();
    notify($actor);
    Sanctum::actingAs($actor);

    $this->getJson('/api/notifications?offset=0&limit=15')
        ->assertOk()
        ->assertJsonStructure([
            'items' => [['id', 'type', 'data', 'read_at', 'created_at']],
            'export_link',
            'pagination' => ['total', 'offset', 'limit', 'total_pages'],
        ])
        ->assertJsonPath('pagination.offset', 0)
        ->assertJsonPath('pagination.limit', 15);
});

it('list: orders by created_at desc and honors offset/limit', function () {
    $actor = User::factory()->create();
    foreach (range(1, 3) as $i) {
        notify($actor, "N{$i}");
    }
    Sanctum::actingAs($actor);

    $this->getJson('/api/notifications?offset=0&limit=2')
        ->assertOk()
        ->assertJsonCount(2, 'items')
        ->assertJsonPath('pagination.total', 3);
});

it('list: unread filter returns only unread notifications', function () {
    $actor = User::factory()->create();
    notify($actor, 'Unread one');
    notify($actor, 'Will be read');
    $actor->notifications()->first()->markAsRead(); // mark the newest read
    Sanctum::actingAs($actor);

    $this->getJson('/api/notifications?filter=unread')
        ->assertOk()
        ->assertJsonPath('pagination.total', 1)
        ->assertJsonCount(1, 'items');
});

it('list: 422 on invalid query param', function () {
    $actor = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/notifications?limit=0')->assertStatus(422);
    $this->getJson('/api/notifications?filter=bogus')->assertStatus(422);
    $this->getJson('/api/notifications?offset=-1')->assertStatus(422);
});

it('list: 401 when unauthenticated', function () {
    $this->getJson('/api/notifications')->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// unread count — GET /api/notifications/unread-count
// ---------------------------------------------------------------------------

it('unread-count: returns the actor unread count', function () {
    $actor = User::factory()->create();
    notify($actor);
    notify($actor);
    Sanctum::actingAs($actor);

    $this->getJson('/api/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.count', 2);
});

it('unread-count: returns the most recent unread notification as latest', function () {
    $actor = User::factory()->create();
    notify($actor, 'Older');
    $this->travel(1)->minutes();
    notify($actor, 'Newest');
    Sanctum::actingAs($actor);

    $this->getJson('/api/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.count', 2)
        ->assertJsonPath('data.latest.data.title', 'Newest')
        ->assertJsonPath('data.latest.read_at', null)
        ->assertJsonStructure([
            'data' => ['count', 'latest' => ['id', 'type', 'data', 'read_at', 'created_at']],
        ]);
});

it('unread-count: latest is null when everything is read', function () {
    $actor = User::factory()->create();
    notify($actor);
    $actor->unreadNotifications->markAsRead();
    Sanctum::actingAs($actor);

    $this->getJson('/api/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.count', 0)
        ->assertJsonPath('data.latest', null);
});

it('unread-count: latest ignores another user notifications', function () {
    $actor = User::factory()->create();
    $other = User::factory()->create();
    notify($actor, 'Mine');
    $this->travel(1)->minutes();
    notify($other, 'Theirs');
    Sanctum::actingAs($actor);

    $this->getJson('/api/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.count', 1)
        ->assertJsonPath('data.latest.data.title', 'Mine');
});

it('unread-count: 401 when unauthenticated', function () {
    $this->getJson('/api/notifications/unread-count')->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// mark one read — PATCH /api/notifications/{notification}/read
// ---------------------------------------------------------------------------

it('mark-one-read: 200 and sets read_at', function () {
    $actor = User::factory()->create();
    notify($actor);
    $id = $actor->notifications()->first()->id;
    Sanctum::actingAs($actor);

    $this->patchJson("/api/notifications/{$id}/read")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $id)
        ->assertJsonPath('data.read_at', fn ($v) => $v !== null);

    expect($actor->fresh()->unreadNotifications()->count())->toBe(0);
});

it('mark-one-read: idempotent on an already-read notification', function () {
    $actor = User::factory()->create();
    notify($actor);
    $id = $actor->notifications()->first()->id;
    Sanctum::actingAs($actor);

    $this->patchJson("/api/notifications/{$id}/read")->assertOk();
    $this->patchJson("/api/notifications/{$id}/read")->assertOk()->assertJsonPath('data.id', $id);
});

it('mark-one-read: 404 for another user notification', function () {
    $actor = User::factory()->create();
    $other = User::factory()->create();
    notify($other);
    $foreignId = $other->notifications()->first()->id;
    Sanctum::actingAs($actor);

    $this->patchJson("/api/notifications/{$foreignId}/read")->assertNotFound();

    // The foreign notification must remain unread.
    expect($other->fresh()->unreadNotifications()->count())->toBe(1);
});

it('mark-one-read: 401 when unauthenticated', function () {
    $this->patchJson('/api/notifications/some-uuid/read')->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// mark all read — POST /api/notifications/read-all
// ---------------------------------------------------------------------------

it('mark-all-read: marks all unread and returns the count', function () {
    $actor = User::factory()->create();
    notify($actor);
    notify($actor);
    notify($actor);
    Sanctum::actingAs($actor);

    $this->postJson('/api/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('data.marked', 3);

    expect($actor->fresh()->unreadNotifications()->count())->toBe(0);
});

it('mark-all-read: returns marked:0 when there is nothing unread', function () {
    $actor = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('data.marked', 0);
});

it('mark-all-read: does not touch another user notifications', function () {
    $actor = User::factory()->create();
    $other = User::factory()->create();
    notify($actor);
    notify($other);
    Sanctum::actingAs($actor);

    $this->postJson('/api/notifications/read-all')->assertOk()->assertJsonPath('data.marked', 1);

    expect($other->fresh()->unreadNotifications()->count())->toBe(1);
});

it('mark-all-read: 401 when unauthenticated', function () {
    $this->postJson('/api/notifications/read-all')->assertUnauthorized();
});
