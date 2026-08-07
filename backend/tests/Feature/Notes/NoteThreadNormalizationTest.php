<?php

use App\Models\Note;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// D-7: single-level thread. Replying to a REPLY re-parents to that reply's
// OWN root (not 422); a parent belonging to a DIFFERENT host record is
// rejected; the child always inherits notable_type/notable_id from the
// root, never from the client (AC-041).

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
    // own Offerte (D-3, `quotes.supervisor_id`), not the GA2 pivot slot.
    function noteManagedOpportunity(User $supervisor): Opportunity
    {
        $opportunity = Opportunity::factory()->create();
        Quote::factory()->for($opportunity)->create(['supervisor_id' => $supervisor->id]);

        return $opportunity;
    }
}

it('replying to a REPLY re-parents to that reply\'s own root, 201 not 422 (AC-041)', function () {
    $actor = noteActor(['request-management.view', 'notes.create']);
    $opportunity = noteManagedOpportunity($actor);
    Sanctum::actingAs($actor);

    $rootId = $this->postJson('/api/notes', [
        'entity_type' => 'request-management', 'entity_id' => $opportunity->id, 'body' => 'root',
    ])->json('data.id');

    $replyId = $this->postJson('/api/notes', [
        'entity_type' => 'request-management', 'entity_id' => $opportunity->id, 'body' => 'reply', 'parent_id' => $rootId,
    ])->json('data.id');

    $grandReplyId = $this->postJson('/api/notes', [
        'entity_type' => 'request-management', 'entity_id' => $opportunity->id, 'body' => 'grand-reply', 'parent_id' => $replyId,
    ])->assertCreated()->json('data.id');

    expect(Note::findOrFail($grandReplyId)->parent_id)->toBe($rootId);
});

it('a parent_id belonging to a DIFFERENT host record -> 422 (AC-041)', function () {
    $actor = noteActor(['request-management.view', 'notes.create']);
    $opportunityA = noteManagedOpportunity($actor);
    $opportunityB = noteManagedOpportunity($actor);
    Sanctum::actingAs($actor);

    $rootOnA = $this->postJson('/api/notes', [
        'entity_type' => 'request-management', 'entity_id' => $opportunityA->id, 'body' => 'root on A',
    ])->json('data.id');

    $this->postJson('/api/notes', [
        'entity_type' => 'request-management',
        'entity_id' => $opportunityB->id,
        'body' => 'cross-entity reply',
        'parent_id' => $rootOnA,
    ])->assertStatus(422)->assertJsonValidationErrors('parent_id');
});

it('a reply always inherits notable_type/notable_id from the root, never from the client (AC-041)', function () {
    $actor = noteActor(['request-management.view', 'notes.create']);
    $opportunity = noteManagedOpportunity($actor);
    Sanctum::actingAs($actor);

    $rootId = $this->postJson('/api/notes', [
        'entity_type' => 'request-management', 'entity_id' => $opportunity->id, 'body' => 'root',
    ])->json('data.id');

    $replyId = $this->postJson('/api/notes', [
        'entity_type' => 'request-management', 'entity_id' => $opportunity->id, 'body' => 'reply', 'parent_id' => $rootId,
    ])->json('data.id');

    $root = Note::findOrFail($rootId);
    $reply = Note::findOrFail($replyId);

    expect($reply->notable_type)->toBe($root->notable_type);
    expect($reply->notable_id)->toBe($root->notable_id);
});
