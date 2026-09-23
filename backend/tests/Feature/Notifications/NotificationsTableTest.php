<?php

declare(strict_types=1);

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// AC-001: any authenticated user, no Spatie permission at all, gets 200 with
// the frozen columns/actions (D-5/data_contract).

it('GET /api/tables/notifications/columns: 200 for any authenticated user, no permission required (AC-001)', function () {
    Sanctum::actingAs(User::factory()->create());

    $data = $this->getJson('/api/tables/notifications/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('notifications');

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe(['id', 'status', 'title', 'message', 'level', 'created_at', 'read_at', 'action_url']);

    $sortable = collect($data['columns'])->filter(fn (array $c): bool => $c['sortable'])->pluck('id')->all();
    expect($sortable)->toEqualCanonicalizing(['id', 'status', 'title', 'level', 'created_at', 'read_at']);

    $filterable = collect($data['columns'])->filter(fn (array $c): bool => $c['filterable'])->pluck('id')->all();
    expect($filterable)->toEqualCanonicalizing(['status', 'title', 'message', 'level', 'created_at', 'read_at']);

    $actionUrl = collect($data['columns'])->firstWhere('id', 'action_url');
    expect($actionUrl['type'])->toBe('link');

    $actionKeys = collect($data['actions'])->pluck('key')->all();
    expect($actionKeys)->toBe(['mark-read', 'mark-unread']);
    expect(collect($data['actions'])->pluck('permission')->filter()->all())->toBe([]);
});

// AC-002: rows are strictly scoped to the caller's own notifiable.

it('POST /api/tables/notifications/rows: only the actor\'s own rows, never another user\'s (AC-002)', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();

    $aIds = Notification::factory()->forUser($a)->count(2)->create()->pluck('id')->all();
    Notification::factory()->forUser($b)->count(3)->create();

    Sanctum::actingAs($a);

    $response = $this->postJson('/api/tables/notifications/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();

    expect($response->json('pagination.total'))->toBe(2);
    expect(collect($response->json('items'))->pluck('id')->all())->toEqualCanonicalizing($aIds);
});

it('POST /api/tables/notifications/rows: 403 without authentication is 401, empty for a valid but foreign row', function () {
    $b = User::factory()->create();
    Notification::factory()->forUser($b)->create();

    $this->postJson('/api/tables/notifications/rows', ['startRow' => 0, 'endRow' => 25])->assertUnauthorized();
});

// AC-003: row shape for unread/read, and the invalid-level -> info fallback.

it('POST /api/tables/notifications/rows: row shape for unread/read, level invalid -> info (AC-003)', function () {
    $actor = User::factory()->create();

    $unread = Notification::factory()->forUser($actor)->state([
        'data' => ['title' => 'Hello', 'message' => 'World', 'level' => 'warning', 'action_url' => '/leads/1'],
    ])->unread()->create();

    $read = Notification::factory()->forUser($actor)->state([
        'data' => ['title' => 'Bye', 'message' => null, 'level' => 'bogus-level', 'action_url' => null],
    ])->read()->create();

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/notifications/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $items = collect($response->json('items'));

    $unreadRow = $items->firstWhere('id', $unread->id);
    expect($unreadRow['status'])->toBe('unread')
        ->and($unreadRow['read_at'])->toBeNull()
        ->and($unreadRow['title'])->toBe('Hello')
        ->and($unreadRow['message'])->toBe('World')
        ->and($unreadRow['level'])->toBe('warning')
        ->and($unreadRow['action_url'])->toBe('/leads/1')
        ->and($unreadRow['actions'])->toBe(['mark-read'])
        ->and($unreadRow['editable'])->toBeFalse();

    $readRow = $items->firstWhere('id', $read->id);
    expect($readRow['status'])->toBe('read')
        ->and($readRow['read_at'])->not->toBeNull()
        ->and($readRow['level'])->toBe('info') // invalid raw value falls back to info
        ->and($readRow['actions'])->toBe(['mark-unread'])
        ->and($readRow['editable'])->toBeFalse();
});

// AC-004: status/level set filters, title text filter, title/message global search.

it('POST /api/tables/notifications/rows: status=unread set filter (AC-004)', function () {
    $actor = User::factory()->create();
    $unread = Notification::factory()->forUser($actor)->unread()->create();
    Notification::factory()->forUser($actor)->read()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/notifications/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['status' => ['filterType' => 'set', 'values' => ['unread']]],
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('id')->all())->toBe([$unread->id]);
});

it('POST /api/tables/notifications/rows: level=warning set filter (AC-004)', function () {
    $actor = User::factory()->create();
    $warning = Notification::factory()->forUser($actor)->level('warning')->create();
    Notification::factory()->forUser($actor)->level('info')->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/notifications/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['level' => ['filterType' => 'set', 'values' => ['warning']]],
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('id')->all())->toBe([$warning->id]);
});

it('POST /api/tables/notifications/rows: text filter on title (AC-004)', function () {
    $actor = User::factory()->create();
    $matching = Notification::factory()->forUser($actor)->state([
        'data' => ['title' => 'Quota exceeded', 'message' => 'x', 'level' => 'info', 'action_url' => null],
    ])->create();
    Notification::factory()->forUser($actor)->state([
        'data' => ['title' => 'Something else', 'message' => 'y', 'level' => 'info', 'action_url' => null],
    ])->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/notifications/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['title' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'quota']],
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('id')->all())->toBe([$matching->id]);
});

it('POST /api/tables/notifications/rows: global search spans title and message (AC-004)', function () {
    $actor = User::factory()->create();
    $byTitle = Notification::factory()->forUser($actor)->state([
        'data' => ['title' => 'Alpha keyword', 'message' => 'x', 'level' => 'info', 'action_url' => null],
    ])->create();
    $byMessage = Notification::factory()->forUser($actor)->state([
        'data' => ['title' => 'y', 'message' => 'contains keyword too', 'level' => 'info', 'action_url' => null],
    ])->create();
    Notification::factory()->forUser($actor)->state([
        'data' => ['title' => 'unrelated', 'message' => 'nothing', 'level' => 'info', 'action_url' => null],
    ])->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/notifications/rows', [
        'startRow' => 0, 'endRow' => 25, 'search' => 'keyword',
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('id')->all())
        ->toEqualCanonicalizing([$byTitle->id, $byMessage->id]);
});

// AC-005: default sort created_at desc; explicit sort by title asc.

it('POST /api/tables/notifications/rows: default sort is created_at desc (AC-005)', function () {
    $actor = User::factory()->create();
    $older = Notification::factory()->forUser($actor)->create(['created_at' => now()->subDays(2)]);
    $newer = Notification::factory()->forUser($actor)->create(['created_at' => now()->subDay()]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/notifications/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();

    expect(collect($response->json('items'))->pluck('id')->all())->toBe([$newer->id, $older->id]);
});

it('POST /api/tables/notifications/rows: sort by title asc (AC-005)', function () {
    $actor = User::factory()->create();
    $b = Notification::factory()->forUser($actor)->state([
        'data' => ['title' => 'Bravo', 'message' => null, 'level' => 'info', 'action_url' => null],
    ])->create();
    $a = Notification::factory()->forUser($actor)->state([
        'data' => ['title' => 'Alpha', 'message' => null, 'level' => 'info', 'action_url' => null],
    ])->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/notifications/rows', [
        'startRow' => 0, 'endRow' => 25,
        'sortModel' => [['colId' => 'title', 'sort' => 'asc']],
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('id')->all())->toBe([$a->id, $b->id]);
});
