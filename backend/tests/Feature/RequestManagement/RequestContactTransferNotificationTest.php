<?php

use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Role;
use App\Models\User;
use App\Notifications\RequestTransferredNotification;
use App\Support\OperationalSiteLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// POST /api/request-management/transfer (spec 0079): notifications —
// AC-013 -> AC-017.

uses(RefreshDatabase::class);

if (! function_exists('transferNotifActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function transferNotifActorWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'update', 'viewAll', 'transferContact'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('transferNotifSite')) {
    function transferNotifSite(): OperationalSite
    {
        return OperationalSite::factory()->withAddress()->create();
    }
}

if (! function_exists('transferNotifSupervisor')) {
    function transferNotifSupervisor(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('supervisor'));

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-013 — the new operator
// ---------------------------------------------------------------------------

it('the new operator receives a database and a mail notification (AC-013)', function () {
    Notification::fake();

    $actor = transferNotifActorWith(['update', 'viewAll', 'transferContact']);
    $destinationSite = transferNotifSite();
    $newOperator = User::factory()->create();
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/transfer', [
        'request_ids' => [$opportunity->id],
        'operational_site_id' => $destinationSite->id,
        'operator_id' => $newOperator->id,
    ])->assertOk();

    Notification::assertSentTo($newOperator, function (RequestTransferredNotification $notification): bool {
        return $notification->via((object) []) === ['database', 'mail'];
    });
});

// ---------------------------------------------------------------------------
// AC-014 — every supervisor, actor always excluded
// ---------------------------------------------------------------------------

it('every `supervisor` receives the notification; the actor never does, even as supervisor or new operator (AC-014)', function () {
    Notification::fake();

    $actor = transferNotifActorWith(['update', 'viewAll', 'transferContact']);
    $actor->assignRole(Role::findOrCreate('supervisor'));
    $supervisor = transferNotifSupervisor();
    $destinationSite = transferNotifSite();
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/transfer', [
        'request_ids' => [$opportunity->id],
        'operational_site_id' => $destinationSite->id,
        // The actor is ALSO the new operator here, on top of being supervisor.
        'operator_id' => $actor->id,
    ])->assertOk();

    Notification::assertSentTo($supervisor, RequestTransferredNotification::class);
    Notification::assertNotSentTo($actor, RequestTransferredNotification::class);
});

// ---------------------------------------------------------------------------
// AC-015 — notification content
// ---------------------------------------------------------------------------

it('the notification body carries contact, origin, destination, operators, author and date (AC-015)', function () {
    Notification::fake();

    $actor = transferNotifActorWith(['update', 'viewAll', 'transferContact']);
    $originSite = transferNotifSite();
    $destinationSite = transferNotifSite();
    $previousOperator = User::factory()->create(['name' => 'Old Operator']);
    $newOperator = User::factory()->create(['name' => 'New Operator']);
    $opportunity = Opportunity::factory()->create([
        'name' => 'Acme deal',
        'operational_site_id' => $originSite->id,
    ]);
    $opportunity->managers()->attach($previousOperator->id, ['position' => Opportunity::OPERATOR_MANAGER_POSITION]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/transfer', [
        'request_ids' => [$opportunity->id],
        'operational_site_id' => $destinationSite->id,
        'operator_id' => $newOperator->id,
    ])->assertOk();

    $originLabel = OperationalSiteLabel::compose($originSite->fresh(['addresses.city'])->primaryAddress);
    $destinationLabel = OperationalSiteLabel::compose($destinationSite->fresh(['addresses.city'])->primaryAddress);

    Notification::assertSentTo(
        $newOperator,
        function (RequestTransferredNotification $notification) use ($newOperator, $actor, $originLabel, $destinationLabel): bool {
            $message = (string) $notification->toArray($newOperator)['message'];

            return str_contains($message, 'Acme deal')
                && str_contains($message, $originLabel)
                && str_contains($message, $destinationLabel)
                && str_contains($message, 'Old Operator')
                && str_contains($message, 'New Operator')
                && str_contains($message, $actor->name);
        },
    );
});

// ---------------------------------------------------------------------------
// AC-016 — no `supervisor` role in the system
// ---------------------------------------------------------------------------

it('with no `supervisor` role in the system, the transfer succeeds and notifies only the operator (AC-016)', function () {
    Notification::fake();

    $actor = transferNotifActorWith(['update', 'viewAll', 'transferContact']);
    $destinationSite = transferNotifSite();
    $newOperator = User::factory()->create();
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/transfer', [
        'request_ids' => [$opportunity->id],
        'operational_site_id' => $destinationSite->id,
        'operator_id' => $newOperator->id,
    ])->assertOk()->assertJsonPath('data.transferred', 1);

    Notification::assertSentTo($newOperator, RequestTransferredNotification::class);
    Notification::assertCount(1);
});

// ---------------------------------------------------------------------------
// AC-017 — action_url is a path, never an absolute URL
// ---------------------------------------------------------------------------

it('action_url is an internal path, never an absolute URL (AC-017)', function () {
    $notification = new RequestTransferredNotification(
        requestId: 42,
        contactLabel: 'Acme deal',
        originSiteLabel: null,
        destinationSiteLabel: 'Sede - Napoli',
        previousOperatorName: null,
        newOperatorName: 'New Operator',
        actorName: 'Actor',
        transferredAt: now(),
    );

    $payload = $notification->toArray((object) []);

    expect($payload['action_url'])->toBe('/request-management/42')
        ->and($payload['action_url'])->not->toStartWith('http');
});
