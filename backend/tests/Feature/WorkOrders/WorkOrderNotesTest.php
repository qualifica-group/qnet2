<?php

use App\Models\Note;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notes\NoteEntityRegistry;
use App\Services\WorkOrders\WorkOrderNotable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Collaborative notes on a Commessa (spec 0134, D-1/D-2)
|--------------------------------------------------------------------------
|
| The WorkOrder registers itself as a host of the agnostic notes component;
| no endpoint is added. Read access is `work-orders.view` AND the membership
| scope (WorkOrderVisibilityScope), the mentionable set is that same rule
| read from the other end. Actors are built WITHOUT `work-orders.viewAll`
| unless the test is about it, so a 403 may mean "not a member".
*/

if (! function_exists('workOrderNoteActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function workOrderNoteActor(array $abilities, bool $canCreateNotes = true): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll', 'viewDocuments'] as $ability) {
            Permission::findOrCreate("work-orders.{$ability}");
        }

        Permission::findOrCreate('notes.create');

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("work-orders.{$ability}");
        }

        if ($canCreateNotes) {
            $user->givePermissionTo('notes.create');
        }

        return $user;
    }
}

if (! function_exists('workOrderWithSupervisor')) {
    function workOrderWithSupervisor(User $supervisor): WorkOrder
    {
        $workOrder = WorkOrder::factory()->create();
        $workOrder->supervisors()->attach($supervisor);

        return $workOrder;
    }
}

it('AC-001: `work-orders` is registered in config/notes.php and resolves to WorkOrderNotable', function () {
    expect(config('notes.notable_types.work-orders'))->toBe(WorkOrderNotable::class)
        ->and(app(NoteEntityRegistry::class)->registeredTypes())->toContain('work-orders');
});

it('AC-001: a WorkOrder owns its notes through the HasNotes morph relation', function () {
    $workOrder = WorkOrder::factory()->create();
    $note = Note::factory()->create(['notable_type' => 'work_order', 'notable_id' => $workOrder->id]);

    expect($workOrder->notes()->pluck('notes.id')->all())->toBe([$note->id]);
});

it('AC-002: supervisor and participant each read the thread of their work order', function (string $role) {
    $actor = workOrderNoteActor(['view']);
    $workOrder = WorkOrder::factory()->create();
    $workOrder->{$role}()->attach($actor, $role === 'participants' ? ['position' => 1] : []);
    Sanctum::actingAs($actor);

    $this->getJson("/api/notes?entity_type=work-orders&entity_id={$workOrder->id}")
        ->assertOk()
        ->assertJsonPath('meta.has_more', false);
})->with(['supervisors', 'participants']);

it('AC-002: work-orders.viewAll reads the thread of a work order the actor has no link to', function () {
    $actor = workOrderNoteActor(['view', 'viewAll']);
    $workOrder = WorkOrder::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/notes?entity_type=work-orders&entity_id={$workOrder->id}")->assertOk();
});

it('AC-003: work-orders.view without membership and without viewAll is 403, and no note leaks', function () {
    $actor = workOrderNoteActor(['view']);
    $workOrder = WorkOrder::factory()->create();
    Note::factory()->create(['notable_type' => 'work_order', 'notable_id' => $workOrder->id, 'body' => 'Riservata']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/notes?entity_type=work-orders&entity_id={$workOrder->id}")->assertForbidden();

    expect($response->getContent())->not->toContain('Riservata');
});

it('AC-003: membership without work-orders.view is 403 — the scope narrows, it does not grant', function () {
    $actor = workOrderNoteActor([]);
    $workOrder = workOrderWithSupervisor($actor);
    Sanctum::actingAs($actor);

    $this->getJson("/api/notes?entity_type=work-orders&entity_id={$workOrder->id}")->assertForbidden();
});

it('AC-004: a member with notes.create writes a note attached to the work order', function () {
    $actor = workOrderNoteActor(['view']);
    $workOrder = workOrderWithSupervisor($actor);
    Sanctum::actingAs($actor);

    $this->postJson('/api/notes', [
        'entity_type' => 'work-orders',
        'entity_id' => $workOrder->id,
        'body' => 'Primo commento',
    ])->assertCreated()->assertJsonPath('data.body', 'Primo commento');

    $this->assertDatabaseHas('notes', [
        'notable_type' => 'work_order',
        'notable_id' => $workOrder->id,
        'user_id' => $actor->id,
    ]);
});

it('AC-004: a member WITHOUT notes.create is 403 and nothing is written', function () {
    $actor = workOrderNoteActor(['view'], canCreateNotes: false);
    $workOrder = workOrderWithSupervisor($actor);
    Sanctum::actingAs($actor);

    $this->postJson('/api/notes', [
        'entity_type' => 'work-orders',
        'entity_id' => $workOrder->id,
        'body' => 'Non deve passare',
    ])->assertForbidden();

    expect(Note::count())->toBe(0);
});

it('AC-005: the mentionable set is exactly the members, plus viewAll holders and super-admins', function () {
    $supervisor = workOrderNoteActor(['view']);
    $participant = workOrderNoteActor(['view']);
    $viewAll = workOrderNoteActor(['view', 'viewAll']);
    $stranger = workOrderNoteActor(['view']);
    $inactive = workOrderNoteActor(['view']);
    $inactive->update(['is_active' => false]);

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(Role::findOrCreate('super-admin'));

    $workOrder = workOrderWithSupervisor($supervisor);
    $workOrder->participants()->attach($participant, ['position' => 1]);
    $workOrder->participants()->attach($inactive, ['position' => 2]);

    Sanctum::actingAs($supervisor);

    $ids = collect($this->getJson("/api/notes/mentionable-users?entity_type=work-orders&entity_id={$workOrder->id}")
        ->assertOk()->json('items'))->pluck('id')->sort()->values()->all();

    expect($ids)->toBe(
        collect([$supervisor->id, $participant->id, $viewAll->id, $superAdmin->id])->sort()->values()->all()
    );
});

it('AC-006: a note submitted with quote_id is 422 and meta.quotes is empty', function () {
    $actor = workOrderNoteActor(['view']);
    $workOrder = workOrderWithSupervisor($actor);
    Sanctum::actingAs($actor);

    $this->postJson('/api/notes', [
        'entity_type' => 'work-orders',
        'entity_id' => $workOrder->id,
        'body' => 'Con scope inesistente',
        'quote_id' => $workOrder->quote_id,
    ])->assertStatus(422);

    expect(Note::count())->toBe(0);

    $this->getJson("/api/notes?entity_type=work-orders&entity_id={$workOrder->id}")
        ->assertOk()
        ->assertJsonPath('meta.quotes', []);
});

it('AC-007: the deep link is /work-orders/{id} for a recipient who sees it, null otherwise', function () {
    $member = workOrderNoteActor(['view']);
    $stranger = workOrderNoteActor(['view']);
    $unpermitted = workOrderNoteActor([]);

    $workOrder = workOrderWithSupervisor($member);
    $workOrder->participants()->attach($unpermitted, ['position' => 1]);
    $notable = app(WorkOrderNotable::class);

    expect($notable->deepLinkPath($workOrder, $member, null))->toBe("/work-orders/{$workOrder->id}")
        ->and($notable->deepLinkPath($workOrder, $stranger, null))->toBeNull()
        ->and($notable->deepLinkPath($workOrder, $unpermitted, null))->toBeNull()
        ->and($notable->label($workOrder))->toBe("{$workOrder->code} — {$workOrder->title}");
});
