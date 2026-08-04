<?php

use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Registry;
use App\Models\User;
use App\Notifications\RecordAssignmentNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0081 — who gets notified when someone is put in charge of a record,
// as Supervisore or as "Gestore Account". AC-001 -> AC-011.

uses(RefreshDatabase::class);

if (! function_exists('assignmentActorWith')) {
    /**
     * @param  array<int, string>  $permissions  fully qualified, e.g. "registries.create"
     */
    function assignmentActorWith(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission));
        }

        return $user;
    }
}

if (! function_exists('assignmentRegistryPayload')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function assignmentRegistryPayload(array $overrides = []): array
    {
        return array_merge([
            'is_supplier' => false,
            'personal_data' => ['type' => 'individual', 'first_name' => 'Ada', 'last_name' => 'Lovelace'],
        ], $overrides);
    }
}

// ---------------------------------------------------------------------------
// AC-001 / AC-002 / AC-003 — the Supervisore slot of an anagrafica
// ---------------------------------------------------------------------------

it('notifies the supervisor named at registry creation, on both channels (AC-001)', function () {
    Notification::fake();

    $actor = assignmentActorWith(['registries.create']);
    $supervisor = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/registries', assignmentRegistryPayload(['supervisor_id' => $supervisor->id]))
        ->assertCreated();

    Notification::assertSentTo($supervisor, function (RecordAssignmentNotification $notification): bool {
        return $notification->via((object) []) === ['database', 'mail'];
    });
});

it('a changed supervisor notifies the new one and leaves the previous one alone (AC-002)', function () {
    Notification::fake();

    $actor = assignmentActorWith(['registries.update']);
    $previous = User::factory()->create();
    $next = User::factory()->create();
    $registry = Registry::factory()->create(['supervisor_id' => $previous->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/registries/{$registry->id}", ['supervisor_id' => $next->id])->assertOk();

    Notification::assertSentTo($next, RecordAssignmentNotification::class);
    Notification::assertNotSentTo($previous, RecordAssignmentNotification::class);
});

it('resubmitting the SAME supervisor notifies nobody (AC-003)', function () {
    Notification::fake();

    $actor = assignmentActorWith(['registries.update']);
    $supervisor = User::factory()->create();
    $registry = Registry::factory()->create(['supervisor_id' => $supervisor->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/registries/{$registry->id}", ['supervisor_id' => $supervisor->id])->assertOk();

    Notification::assertNothingSent();
});

// ---------------------------------------------------------------------------
// AC-004 / AC-005 / AC-006 — the "Gestore Account" slots of an anagrafica
// ---------------------------------------------------------------------------

it('notifies a manager attached to ANY slot, carrying that slot number (AC-004)', function () {
    Notification::fake();

    $actor = assignmentActorWith(['registries.update', 'registries.view']);
    $manager = User::factory()->create();
    $registry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    // Two leading empty slots: the manager lands on "G.A. 3".
    $this->patchJson("/api/registries/{$registry->id}", ['manager_slots' => [null, null, $manager->id]])
        ->assertOk();

    Notification::assertSentTo($manager, function (RecordAssignmentNotification $notification) use ($manager): bool {
        return str_contains((string) $notification->toArray($manager)['message'], '3');
    });
});

it('moving an existing manager between slots notifies nobody (AC-005)', function () {
    Notification::fake();

    $actor = assignmentActorWith(['registries.update']);
    $manager = User::factory()->create();
    $registry = Registry::factory()->create();
    $registry->managers()->sync([$manager->id => ['position' => 1]]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/registries/{$registry->id}", ['manager_slots' => [null, $manager->id]])->assertOk();

    Notification::assertNothingSent();
});

it('removing a manager or a supervisor notifies nobody (AC-006)', function () {
    Notification::fake();

    $actor = assignmentActorWith(['registries.update']);
    $manager = User::factory()->create();
    $supervisor = User::factory()->create();
    $registry = Registry::factory()->create(['supervisor_id' => $supervisor->id]);
    $registry->managers()->sync([$manager->id => ['position' => 1]]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/registries/{$registry->id}", [
        'manager_slots' => [],
        'supervisor_id' => null,
    ])->assertOk();

    Notification::assertNothingSent();
});

// ---------------------------------------------------------------------------
// AC-007 — the actor never notifies itself
// ---------------------------------------------------------------------------

it('the actor is never notified, not even when assigning themselves (AC-007)', function () {
    Notification::fake();

    $actor = assignmentActorWith(['registries.update']);
    $registry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/registries/{$registry->id}", [
        'supervisor_id' => $actor->id,
        'manager_slots' => [$actor->id],
    ])->assertOk();

    Notification::assertNothingSent();
});

// ---------------------------------------------------------------------------
// AC-008 — the same rules on an Opportunity
// ---------------------------------------------------------------------------

it('applies the same supervisor and manager rules to an opportunity (AC-008)', function () {
    Notification::fake();

    $actor = assignmentActorWith(['opportunities.update']);
    $supervisor = User::factory()->create();
    $manager = User::factory()->create();
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunities/{$opportunity->id}", [
        'supervisor_id' => $supervisor->id,
        'manager_slots' => [$manager->id],
    ])->assertOk();

    Notification::assertSentTo($supervisor, RecordAssignmentNotification::class);
    Notification::assertSentTo($manager, RecordAssignmentNotification::class);
});

// ---------------------------------------------------------------------------
// AC-009 / AC-010 / AC-011 — the GA2 "Operatore" of request management
// ---------------------------------------------------------------------------

it('notifies the operator assigned from the work panel, at the GA2 slot (AC-009)', function () {
    Notification::fake();

    $actor = assignmentActorWith(['request-management.view', 'request-management.viewAll', 'request-management.update', 'request-management.assignOperator']);
    $operator = User::factory()->create();
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", ['operator_id' => $operator->id])->assertOk();

    Notification::assertSentTo($operator, function (RecordAssignmentNotification $notification) use ($operator): bool {
        return str_contains(
            (string) $notification->toArray($operator)['message'],
            (string) Opportunity::OPERATOR_MANAGER_POSITION,
        );
    });
});

it('a bulk assignment of N requests produces N notifications for the operator (AC-010)', function () {
    Notification::fake();

    $actor = assignmentActorWith(['request-management.view', 'request-management.viewAll', 'request-management.update', 'request-management.assignOperator']);
    $operator = User::factory()->create();
    $requests = Opportunity::factory()->count(3)->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => $requests->modelKeys(),
        'operational_site_id' => OperationalSite::factory()->withAddress()->create()->id,
        'mode' => 'single',
        'operator_id' => $operator->id,
    ])->assertOk();

    Notification::assertSentToTimes($operator, RecordAssignmentNotification::class, 3);
});

it('reassigning the operator to the user already holding the slot notifies nobody (AC-011)', function () {
    Notification::fake();

    $actor = assignmentActorWith(['request-management.view', 'request-management.viewAll', 'request-management.update', 'request-management.assignOperator']);
    $operator = User::factory()->create();
    $opportunity = Opportunity::factory()->create();
    $opportunity->managers()->attach($operator->id, ['position' => Opportunity::OPERATOR_MANAGER_POSITION]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", ['operator_id' => $operator->id])->assertOk();

    Notification::assertNothingSent();
});
