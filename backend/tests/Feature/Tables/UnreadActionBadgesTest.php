<?php

use App\Models\Attachment;
use App\Models\Note;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Row action badges count only what the user has not read (2026-11-18)
|--------------------------------------------------------------------------
|
| The `notes` and `documents` badges of the grids' Azioni column show the
| notes/documents the actor has not read yet: never their own, never the ones
| migrated from the legacy QNet. Opening the list (POST .../read) marks it read.
*/

if (! function_exists('unreadBadgeActor')) {
    function unreadBadgeActor(bool $canViewRegistry = true): User
    {
        foreach (['viewAny', 'view', 'viewDocuments'] as $ability) {
            Permission::findOrCreate("registries.{$ability}");
        }
        Permission::findOrCreate('attachments.viewAny');

        $user = User::factory()->create();
        $user->givePermissionTo(['registries.viewAny', 'registries.viewDocuments', 'attachments.viewAny']);

        if ($canViewRegistry) {
            $user->givePermissionTo('registries.view');
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    function unreadBadgeRow(object $test, Registry $registry): array
    {
        return collect($test->postJson('/api/tables/registries/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items'))
            ->firstWhere('id', $registry->id);
    }

    function unreadBadgeDocument(Registry $registry, array $attributes = []): Attachment
    {
        return Attachment::factory()->create([
            'attachable_type' => 'registry',
            'attachable_id' => $registry->id,
            'collection' => 'documents',
            ...$attributes,
        ]);
    }
}

it('counts only the notes of others not read yet: own and legacy notes never count', function () {
    $actor = unreadBadgeActor();
    $registry = Registry::factory()->create();
    $root = Note::factory()->create(['notable_type' => 'registry', 'notable_id' => $registry->id]);
    Note::factory()->create(['notable_type' => 'registry', 'notable_id' => $registry->id, 'parent_id' => $root->id]);
    Note::factory()->create(['notable_type' => 'registry', 'notable_id' => $registry->id, 'user_id' => $actor->id]);
    Note::factory()->create(['notable_type' => 'registry', 'notable_id' => $registry->id, 'old_id' => 991]);
    Sanctum::actingAs($actor);

    expect(unreadBadgeRow($this, $registry)['notes_count'])->toBe(2);
});

it('POST /api/notes/read marks the whole thread read, replies included, and the badge drops to zero', function () {
    $actor = unreadBadgeActor();
    $registry = Registry::factory()->create();
    $root = Note::factory()->create(['notable_type' => 'registry', 'notable_id' => $registry->id]);
    Note::factory()->create(['notable_type' => 'registry', 'notable_id' => $registry->id, 'parent_id' => $root->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/notes/read', ['entity_type' => 'registries', 'entity_id' => $registry->id])
        ->assertOk()
        ->assertJsonPath('data.marked', 2);

    expect(unreadBadgeRow($this, $registry)['notes_count'])->toBe(0);

    // Idempotent: a second open marks nothing new.
    $this->postJson('/api/notes/read', ['entity_type' => 'registries', 'entity_id' => $registry->id])
        ->assertOk()
        ->assertJsonPath('data.marked', 0);
});

it('a note read by one user stays unread for another, and a new note counts again', function () {
    $reader = unreadBadgeActor();
    $other = unreadBadgeActor();
    $registry = Registry::factory()->create();
    Note::factory()->create(['notable_type' => 'registry', 'notable_id' => $registry->id]);

    Sanctum::actingAs($reader);
    $this->postJson('/api/notes/read', ['entity_type' => 'registries', 'entity_id' => $registry->id])->assertOk();
    Note::factory()->create(['notable_type' => 'registry', 'notable_id' => $registry->id]);

    expect(unreadBadgeRow($this, $registry)['notes_count'])->toBe(1);

    Sanctum::actingAs($other);
    expect(unreadBadgeRow($this, $registry)['notes_count'])->toBe(2);
});

it('POST /api/notes/read needs read access to the host record and a registered entity type', function () {
    $registry = Registry::factory()->create();
    Note::factory()->create(['notable_type' => 'registry', 'notable_id' => $registry->id]);
    $actor = unreadBadgeActor(canViewRegistry: false);
    Sanctum::actingAs($actor);

    $this->postJson('/api/notes/read', ['entity_type' => 'registries', 'entity_id' => $registry->id])->assertForbidden();
    $this->postJson('/api/notes/read', ['entity_type' => 'nope', 'entity_id' => $registry->id])->assertUnprocessable();

    expect(Note::query()->unreadBy($actor->id)->count())->toBe(1);
});

it('counts only the documents of others not read yet, and POST /api/attachments/read clears them', function () {
    $actor = unreadBadgeActor();
    $registry = Registry::factory()->create();
    unreadBadgeDocument($registry);
    unreadBadgeDocument($registry);
    unreadBadgeDocument($registry, ['uploaded_by' => $actor->id]);
    unreadBadgeDocument($registry, ['old_id' => 77]);
    unreadBadgeDocument($registry, ['collection' => 'other']);
    Sanctum::actingAs($actor);

    expect(unreadBadgeRow($this, $registry)['documents_count'])->toBe(2);

    $this->postJson('/api/attachments/read', [
        'attachable_type' => 'registry',
        'attachable_id' => $registry->id,
        'collection' => 'documents',
    ])->assertOk()->assertJsonPath('data.marked', 2);

    expect(unreadBadgeRow($this, $registry)['documents_count'])->toBe(0);
});

it('POST /api/attachments/read is authorized like the list', function () {
    $registry = Registry::factory()->create();
    unreadBadgeDocument($registry);
    $actor = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/attachments/read', [
        'attachable_type' => 'registry',
        'attachable_id' => $registry->id,
        'collection' => 'documents',
    ])->assertForbidden();

    $this->postJson('/api/attachments/read', ['attachable_type' => 'nope', 'attachable_id' => $registry->id])
        ->assertUnprocessable();

    expect(Attachment::query()->unreadBy($actor->id)->count())->toBe(1);
});
