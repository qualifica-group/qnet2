<?php

use App\Models\Note;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// D-10/D-12: the body-token/mentions array coherence AND the mentionable-set
// boundary are both enforced server-side, in either direction (AC-051/052).

uses(RefreshDatabase::class);

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
     * Grants $user D-10 mentionable access to $opportunity WITHOUT making
     * them supervise a second Offerta of it: qualifies via the OTHER D-10
     * branch instead — `request-management.viewAll`.
     */
    function grantMentionAccess(Opportunity $opportunity, User $user): void
    {
        $user->givePermissionTo(['request-management.view', 'request-management.viewAll']);
    }
}

// ---------------------------------------------------------------------------
// AC-052 — token/mentions coherence
// ---------------------------------------------------------------------------

it('a body token without a matching mentions[] entry -> 422 (AC-052)', function () {
    $actor = noteActor(['request-management.view', 'notes.create']);
    $opportunity = noteManagedOpportunity($actor);
    Sanctum::actingAs($actor);

    $this->postJson('/api/notes', [
        'entity_type' => 'request-management',
        'entity_id' => $opportunity->id,
        'body' => 'Hey @[Tizio](user:7)',
        'mentions' => [],
    ])->assertStatus(422)->assertJsonValidationErrors('mentions');
});

it('a mentions[] entry without a matching body token -> 422 (AC-052)', function () {
    $actor = noteActor(['request-management.view', 'notes.create']);
    $opportunity = noteManagedOpportunity($actor);
    $mentioned = User::factory()->create();
    grantMentionAccess($opportunity, $mentioned);
    Sanctum::actingAs($actor);

    $this->postJson('/api/notes', [
        'entity_type' => 'request-management',
        'entity_id' => $opportunity->id,
        'body' => 'Hey there, no mention here',
        'mentions' => [$mentioned->id],
    ])->assertStatus(422)->assertJsonValidationErrors('mentions');
});

it('matching token and mentions[] -> 201, mentions ordered by first appearance, a repeated token counts once (AC-052)', function () {
    $actor = noteActor(['request-management.view', 'notes.create']);
    $opportunity = noteManagedOpportunity($actor);
    $mentioned = User::factory()->create(['name' => 'Tizio Caio']);
    grantMentionAccess($opportunity, $mentioned);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/notes', [
        'entity_type' => 'request-management',
        'entity_id' => $opportunity->id,
        'body' => "Hey @[Tizio Caio](user:{$mentioned->id}) and again @[Tizio Caio](user:{$mentioned->id})",
        'mentions' => [$mentioned->id],
    ])->assertCreated();

    expect($response->json('data.mentions'))->toBe([['id' => $mentioned->id, 'name' => 'Tizio Caio', 'avatar_url' => null]]);

    $noteId = $response->json('data.id');
    expect(DB::table('note_mentions')->where('note_id', $noteId)->count())->toBe(1);
});

it('a mention carries the user avatar so the chip matches the avatar shown elsewhere', function () {
    Storage::fake('local');

    $actor = noteActor(['request-management.view', 'notes.create']);
    $opportunity = noteManagedOpportunity($actor);
    $mentioned = User::factory()->create(['name' => 'Tizio Caio']);
    $mentioned->attach(UploadedFile::fake()->image('avatar.png'), User::AVATAR_COLLECTION);
    grantMentionAccess($opportunity, $mentioned);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/notes', [
        'entity_type' => 'request-management',
        'entity_id' => $opportunity->id,
        'body' => "Hey @[Tizio Caio](user:{$mentioned->id})",
        'mentions' => [$mentioned->id],
    ])->assertCreated();

    $mention = $response->json('data.mentions.0');

    expect($mention['id'])->toBe($mentioned->id)
        ->and($mention['avatar_url'])->toStartWith('data:image/')
        ->and($mention['avatar_url'])->toContain(';base64,');
});

// ---------------------------------------------------------------------------
// AC-051 — mentionable-set enforcement, server-side, regardless of the token
// ---------------------------------------------------------------------------

it('a mention outside the mentionable set -> 422, no note created, no notification (AC-051)', function () {
    Notification::fake();

    $actor = noteActor(['request-management.view', 'notes.create']);
    $opportunity = noteManagedOpportunity($actor);
    $outsider = User::factory()->create(); // no request-management.view, not a manager
    Sanctum::actingAs($actor);

    $countBefore = Note::count();

    $this->postJson('/api/notes', [
        'entity_type' => 'request-management',
        'entity_id' => $opportunity->id,
        'body' => "Hey @[Outsider](user:{$outsider->id})",
        'mentions' => [$outsider->id],
    ])->assertStatus(422)->assertJsonValidationErrors('mentions');

    expect(Note::count())->toBe($countBefore);
    Notification::assertNothingSent();
});

it('an inactive or nonexistent mentioned user -> 422 (AC-051)', function () {
    $actor = noteActor(['request-management.view', 'notes.create']);
    $opportunity = noteManagedOpportunity($actor);
    $inactive = User::factory()->create(['is_active' => false]);
    grantMentionAccess($opportunity, $inactive);
    Sanctum::actingAs($actor);

    $this->postJson('/api/notes', [
        'entity_type' => 'request-management',
        'entity_id' => $opportunity->id,
        'body' => "Hey @[Inactive](user:{$inactive->id})",
        'mentions' => [$inactive->id],
    ])->assertStatus(422)->assertJsonValidationErrors('mentions');

    $nonexistentId = $inactive->id + 999999;

    $this->postJson('/api/notes', [
        'entity_type' => 'request-management',
        'entity_id' => $opportunity->id,
        'body' => "Hey @[Ghost](user:{$nonexistentId})",
        'mentions' => [$nonexistentId],
    ])->assertStatus(422)->assertJsonValidationErrors('mentions');
});
