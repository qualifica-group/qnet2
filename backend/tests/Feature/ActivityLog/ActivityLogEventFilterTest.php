<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0034 follow-up (AC-021..AC-023): the timeline can be narrowed to a
 * single event via `?event=`. Filtering happens server-side so the keyset
 * pages stay full — see AggregatedActivityService::applyEvent.
 */
if (! function_exists('activityLogEventFilterActor')) {
    function activityLogEventFilterActor(): User
    {
        foreach (['view', 'viewActivity'] as $ability) {
            Permission::findOrCreate("users.{$ability}");
        }

        $actor = User::factory()->create();
        $actor->givePermissionTo(['users.view', 'users.viewActivity']);

        return $actor;
    }
}

it('returns every event when no filter is given (AC-021)', function () {
    Sanctum::actingAs(activityLogEventFilterActor());
    $target = User::factory()->create(['is_active' => true]);
    $target->update(['is_active' => false]);

    $events = $this->getJson("/api/activity-log/users/{$target->id}")
        ->assertOk()
        ->json('data.items.*.event');

    expect($events)->toContain('created')->toContain('updated');
});

it('returns only updated entries when event=updated (AC-021)', function () {
    Sanctum::actingAs(activityLogEventFilterActor());
    $target = User::factory()->create(['is_active' => true]);
    $target->update(['is_active' => false]);

    $events = $this->getJson("/api/activity-log/users/{$target->id}?event=updated")
        ->assertOk()
        ->json('data.items.*.event');

    expect($events)->not->toBeEmpty()->each->toBe('updated');
});

it('returns only created entries when event=created (AC-021)', function () {
    Sanctum::actingAs(activityLogEventFilterActor());
    $target = User::factory()->create(['is_active' => true]);
    $target->update(['is_active' => false]);

    $events = $this->getJson("/api/activity-log/users/{$target->id}?event=created")
        ->assertOk()
        ->json('data.items.*.event');

    expect($events)->toBe(['created']);
});

it('keeps the keyset pages full while filtering, without repeating rows (AC-022)', function () {
    Sanctum::actingAs(activityLogEventFilterActor());
    $target = User::factory()->create(['is_active' => true]);

    // 1 `created` (factory) + 4 `updated`.
    $value = true;
    for ($i = 0; $i < 4; $i++) {
        $value = ! $value;
        $target->update(['is_active' => $value]);
    }

    $page1 = $this->getJson("/api/activity-log/users/{$target->id}?event=updated&per_page=2")
        ->assertOk()
        ->json('data');

    expect($page1['items'])->toHaveCount(2)
        ->and($page1['next_cursor'])->not->toBeNull();

    $page2 = $this->getJson(
        "/api/activity-log/users/{$target->id}?event=updated&per_page=2&cursor={$page1['next_cursor']}"
    )->assertOk()->json('data');

    $ids = array_merge(array_column($page1['items'], 'id'), array_column($page2['items'], 'id'));

    expect($page2['items'])->toHaveCount(2)
        ->and($page2['next_cursor'])->toBeNull()
        ->and($ids)->toHaveCount(4)
        ->and(array_unique($ids))->toHaveCount(4);
});

it('rejects an event outside the allow-list with 422 (AC-023)', function () {
    Sanctum::actingAs(activityLogEventFilterActor());
    $target = User::factory()->create();

    $this->getJson("/api/activity-log/users/{$target->id}?event=DROP+TABLE")
        ->assertStatus(422)
        ->assertJsonValidationErrors('event');
});
