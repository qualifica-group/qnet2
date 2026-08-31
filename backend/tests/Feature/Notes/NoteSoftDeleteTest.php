<?php

use App\Models\Note;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// D-8: deletion is SOFT and never touches replies — the root and every one
// of its replies remain in the database, they simply stop being reachable
// once the root no longer appears in the list (AC-033).

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

it('DELETE on a root soft-deletes it, hides it AND its replies from the list, and destroys no row (AC-033)', function () {
    $actor = noteActor(['request-management.view', 'notes.create']);
    $opportunity = noteManagedOpportunity($actor);
    Sanctum::actingAs($actor);

    $rootId = $this->postJson('/api/notes', [
        'entity_type' => 'request-management',
        'entity_id' => $opportunity->id,
        'body' => 'Root note',
    ])->json('data.id');

    $this->postJson('/api/notes', [
        'entity_type' => 'request-management',
        'entity_id' => $opportunity->id,
        'body' => 'Reply',
        'parent_id' => $rootId,
    ])->assertCreated();

    $totalBefore = Note::withTrashed()->count();

    $this->deleteJson("/api/notes/{$rootId}")->assertOk();

    expect(Note::withTrashed()->count())->toBe($totalBefore);
    expect(Note::withTrashed()->findOrFail($rootId)->deleted_at)->not->toBeNull();

    $this->getJson("/api/notes?entity_type=request-management&entity_id={$opportunity->id}")
        ->assertOk()
        ->assertJsonPath('data', []);
});

it('PATCH/DELETE on an already soft-deleted note -> 404 (data_contract)', function () {
    $actor = noteActor(['request-management.view', 'notes.create']);
    $opportunity = noteManagedOpportunity($actor);
    Sanctum::actingAs($actor);

    $noteId = $this->postJson('/api/notes', [
        'entity_type' => 'request-management',
        'entity_id' => $opportunity->id,
        'body' => 'Root note',
    ])->json('data.id');

    $this->deleteJson("/api/notes/{$noteId}")->assertOk();

    $this->patchJson("/api/notes/{$noteId}", ['body' => 'Too late'])->assertNotFound();
    $this->deleteJson("/api/notes/{$noteId}")->assertNotFound();
});
