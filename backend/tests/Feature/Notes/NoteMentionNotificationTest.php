<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use App\Notifications\NoteMentionNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/*
|--------------------------------------------------------------------------
| AC-060 / AC-061 / AC-062 — mention notification dispatch
|--------------------------------------------------------------------------
|
| Deliberately NOT using RefreshDatabase: NoteService dispatches
| NoteMentionNotification via DB::afterCommit (constraints: "dopo il commit
| della nota"). Laravel only executes afterCommit callbacks once the
| OUTERMOST transaction commits — RefreshDatabase wraps every test in its
| OWN transaction that is always rolled back, never committed, which would
| silently discard every afterCommit callback and turn these assertions into
| false negatives. Each test calls migrate:fresh itself, so the file is
| independently runnable and leaves a clean, fully-migrated database behind.
|
| GUARD (non-negotiable): migrate:fresh is destructive. phpunit.xml pins
| DB_CONNECTION=sqlite / DB_DATABASE=:memory: for the WHOLE test run, but
| this project has no .env.testing, so anything that changes how the
| testing environment resolves its connection (a stray --env=testing flag,
| a misconfigured CI runner, ...) would silently point this at the REAL
| database instead. Refuse to run rather than risk it.
*/

if (! function_exists('assertSafeToWipeDatabase')) {
    function assertSafeToWipeDatabase(): void
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'sqlite' || $connection->getDatabaseName() !== ':memory:') {
            throw new RuntimeException(
                'Refusing to run migrate:fresh: the active connection is not an in-memory SQLite '
                .'database ('.$connection->getDriverName().':'.$connection->getDatabaseName().'). '
                .'This guard exists because migrate:fresh against a real database would destroy it.'
            );
        }
    }
}

beforeEach(function () {
    assertSafeToWipeDatabase();
    Artisan::call('migrate:fresh');
});

if (! function_exists('noteActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function noteActor(array $abilities = []): User
    {
        foreach (['request-management.view', 'request-management.viewAll', 'notes.create'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo($ability);
        }

        return $user;
    }
}

if (! function_exists('noteManagedOpportunity')) {
    // Spec 0086, D-9: read/mention access is re-keyed on the Opportunity's
    // own Offerte (spec 0087, D-9, `quotes.operator_id`), not the GA2 pivot slot.
    function noteManagedOpportunity(User $operator): Opportunity
    {
        $opportunity = Opportunity::factory()->create();
        Quote::factory()->for($opportunity)->create(['operator_id' => $operator->id]);

        return $opportunity;
    }
}

if (! function_exists('grantMentionAccess')) {
    /**
     * Grants $user D-10 mentionable access to $opportunity WITHOUT the GA2
     * manager pivot: `opportunity_user` has a UNIQUE(opportunity_id,
     * position) constraint (only one Account Manager per position per
     * opportunity), so a second mentionable user on the SAME opportunity
     * that noteManagedOpportunity() already assigned must qualify via the
     * OTHER D-10 branch instead — `request-management.viewAll`.
     */
    function grantMentionAccess(Opportunity $opportunity, User $user): void
    {
        $user->givePermissionTo(['request-management.view', 'request-management.viewAll']);
    }
}

it('mentioning two users notifies both, once each, never the self-mentioning author (AC-060)', function () {
    Notification::fake();

    $actor = noteActor(['request-management.view', 'notes.create']);
    $opportunity = noteManagedOpportunity($actor);

    $mentionedOne = User::factory()->create(['name' => 'Uno']);
    $mentionedTwo = User::factory()->create(['name' => 'Due']);
    grantMentionAccess($opportunity, $mentionedOne);
    grantMentionAccess($opportunity, $mentionedTwo);

    Sanctum::actingAs($actor);

    $this->postJson('/api/notes', [
        'entity_type' => 'request-management',
        'entity_id' => $opportunity->id,
        'body' => "Hey @[Uno](user:{$mentionedOne->id}) and @[Due](user:{$mentionedTwo->id}), also myself @[Me](user:{$actor->id})",
        'mentions' => [$mentionedOne->id, $mentionedTwo->id, $actor->id],
    ])->assertCreated();

    Notification::assertSentToTimes($mentionedOne, NoteMentionNotification::class, 1);
    Notification::assertSentToTimes($mentionedTwo, NoteMentionNotification::class, 1);
    Notification::assertNotSentTo($actor, NoteMentionNotification::class);
});

it('the database payload matches NotificationData and surfaces via the existing notifications endpoint (AC-061)', function () {
    $actor = noteActor(['request-management.view', 'notes.create']);
    $opportunity = noteManagedOpportunity($actor);

    $mentioned = User::factory()->create(['name' => 'Mentioned Person']);
    grantMentionAccess($opportunity, $mentioned);

    Sanctum::actingAs($actor);

    $this->postJson('/api/notes', [
        'entity_type' => 'request-management',
        'entity_id' => $opportunity->id,
        'body' => "Hey @[Mentioned Person](user:{$mentioned->id})",
        'mentions' => [$mentioned->id],
    ])->assertCreated();

    $row = DB::table('notifications')->where('notifiable_id', $mentioned->id)->first();
    expect($row)->not->toBeNull();
    expect($row->type)->toBe(NoteMentionNotification::class);

    $payload = json_decode($row->data, true);
    expect(array_keys($payload))->toEqualCanonicalizing(['title', 'message', 'level', 'action_url']);
    expect($payload['level'])->toBe('info');
    // The note carries no `quote_id` (general note) and this recipient holds
    // only `request-management.*`, so the module's own list is the landing
    // page (decisione utente 2026-09-07). A PATH, never an absolute URL: the
    // campanella rejects anything else (safe-internal-path.ts).
    expect($payload['action_url'])->toBe('/request-management')
        ->and($payload['action_url'])->not->toStartWith('http');
    expect($payload['message'])->toContain($actor->name);
    expect($payload['message'])->toContain($opportunity->name);
    expect($payload['message'])->toContain('@Mentioned Person');

    Sanctum::actingAs($mentioned);
    $list = $this->getJson('/api/notifications')->assertOk();
    expect(collect($list->json('items'))->pluck('id'))->toContain($row->id);

    $unread = $this->getJson('/api/notifications/unread-count')->assertOk();
    expect($unread->json('data.count'))->toBeGreaterThanOrEqual(1);
});

it('updating mentions notifies only the NEW ones, drops the pivot row for removed ones without deleting the past notification (AC-062)', function () {
    Notification::fake();

    $actor = noteActor(['request-management.view', 'notes.create']);
    $opportunity = noteManagedOpportunity($actor);

    $kept = User::factory()->create(['name' => 'Kept']);
    $removed = User::factory()->create(['name' => 'Removed']);
    $new = User::factory()->create(['name' => 'New']);
    grantMentionAccess($opportunity, $kept);
    grantMentionAccess($opportunity, $removed);
    grantMentionAccess($opportunity, $new);

    Sanctum::actingAs($actor);

    $noteId = $this->postJson('/api/notes', [
        'entity_type' => 'request-management',
        'entity_id' => $opportunity->id,
        'body' => "Hey @[Kept](user:{$kept->id}) and @[Removed](user:{$removed->id})",
        'mentions' => [$kept->id, $removed->id],
    ])->assertCreated()->json('data.id');

    Notification::assertSentToTimes($kept, NoteMentionNotification::class, 1);
    Notification::assertSentToTimes($removed, NoteMentionNotification::class, 1);

    $this->patchJson("/api/notes/{$noteId}", [
        'body' => "Hey @[Kept](user:{$kept->id}) and @[New](user:{$new->id})",
        'mentions' => [$kept->id, $new->id],
    ])->assertOk();

    // Still exactly one for $kept (not renotified); exactly one for the newly attached $new.
    Notification::assertSentToTimes($kept, NoteMentionNotification::class, 1);
    Notification::assertSentToTimes($new, NoteMentionNotification::class, 1);

    expect(DB::table('note_mentions')->where('note_id', $noteId)->where('user_id', $kept->id)->exists())->toBeTrue();
    expect(DB::table('note_mentions')->where('note_id', $noteId)->where('user_id', $removed->id)->exists())->toBeFalse();
    expect(DB::table('note_mentions')->where('note_id', $noteId)->where('user_id', $new->id)->exists())->toBeTrue();
});
