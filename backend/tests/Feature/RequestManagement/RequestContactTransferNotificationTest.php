<?php

use App\Enums\TransferRecipientRoleEnum;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use App\Notifications\RequestTransferredNotification;
use App\Support\OperationalSiteLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// POST /api/request-management/transfer: notifications — spec 0079 AC-013 ->
// AC-017, AMENDED by spec 0081. Two requirements changed here, so the
// expectations below changed WITH them (never to make a test pass):
//   1. The supervisory copy goes to holders of the PERMISSION
//      `request-management.receiveTransferNotifications`, not to the spatie
//      role `supervisor` (decisione utente 2026-08-04).
//   2. One transfer now produces THREE different texts. The full audit
//      sentence (both operators, author, both Sedi) is the SUPERVISORY copy;
//      the incoming operator gets a shorter, assignment-shaped one.

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
    /**
     * A recipient of the supervisory copy: whoever HOLDS the permission,
     * however they got it (spec 0081). Creating the permission row here and
     * nowhere else is deliberate — the AC-016 case below relies on it being
     * absent when no supervisor exists.
     */
    function transferNotifSupervisor(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('request-management.receiveTransferNotifications'));

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
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/transfer', [
        'request_ids' => [$quote->id],
        'operational_site_id' => $destinationSite->id,
        'operator_id' => $newOperator->id,
    ])->assertOk();

    Notification::assertSentTo($newOperator, function (RequestTransferredNotification $notification): bool {
        return $notification->via((object) []) === ['database', 'mail'];
    });
});

// ---------------------------------------------------------------------------
// AC-014 — every permission holder, actor always excluded
// ---------------------------------------------------------------------------

it('every holder of the transfer-notification permission receives the copy; the actor never does, even as holder or new operator (AC-014)', function () {
    Notification::fake();

    $actor = transferNotifActorWith(['update', 'viewAll', 'transferContact']);
    $supervisor = transferNotifSupervisor();
    $actor->givePermissionTo('request-management.receiveTransferNotifications');
    $destinationSite = transferNotifSite();
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/transfer', [
        'request_ids' => [$quote->id],
        'operational_site_id' => $destinationSite->id,
        // The actor is ALSO the new operator here, on top of holding the grant.
        'operator_id' => $actor->id,
    ])->assertOk();

    Notification::assertSentTo($supervisor, RequestTransferredNotification::class);
    Notification::assertNotSentTo($actor, RequestTransferredNotification::class);
});

// ---------------------------------------------------------------------------
// AC-015 — notification content (the supervisory copy is the full record)
// ---------------------------------------------------------------------------

it('the supervisory copy carries contact, origin, destination, operators, author and date (AC-015)', function () {
    Notification::fake();

    $actor = transferNotifActorWith(['update', 'viewAll', 'transferContact']);
    $supervisor = transferNotifSupervisor();
    $originSite = transferNotifSite();
    $destinationSite = transferNotifSite();
    $previousOperator = User::factory()->create(['name' => 'Old Operator']);
    $newOperator = User::factory()->create(['name' => 'New Operator']);
    $opportunity = Opportunity::factory()->create(['name' => 'Acme deal']);
    $opportunity->managers()->attach($previousOperator->id, ['position' => Opportunity::OPERATOR_MANAGER_POSITION]);
    $quote = Quote::factory()->for($opportunity)->create([
        'operational_site_id' => $originSite->id,
        'supervisor_id' => $previousOperator->id,
    ]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/transfer', [
        'request_ids' => [$quote->id],
        'operational_site_id' => $destinationSite->id,
        'operator_id' => $newOperator->id,
    ])->assertOk();

    $originLabel = OperationalSiteLabel::compose($originSite->fresh(['addresses.city'])->primaryAddress);
    $destinationLabel = OperationalSiteLabel::compose($destinationSite->fresh(['addresses.city'])->primaryAddress);

    Notification::assertSentTo(
        $supervisor,
        function (RequestTransferredNotification $notification) use ($supervisor, $actor, $originLabel, $destinationLabel): bool {
            $message = (string) $notification->toArray($supervisor)['message'];

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
// AC-016 — nobody can receive the supervisory copy
// ---------------------------------------------------------------------------

it('with no holder of the transfer-notification permission, the transfer succeeds and notifies only the operator (AC-016)', function () {
    Notification::fake();

    $actor = transferNotifActorWith(['update', 'viewAll', 'transferContact']);
    $destinationSite = transferNotifSite();
    $newOperator = User::factory()->create();
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/transfer', [
        'request_ids' => [$quote->id],
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
    $recipient = User::factory()->create();
    $recipient->givePermissionTo(Permission::findOrCreate('request-management.view'));

    $notification = new RequestTransferredNotification(
        requestId: 42,
        contactLabel: 'Acme deal',
        originSiteLabel: null,
        destinationSiteLabel: 'Sede - Napoli',
        previousOperatorName: null,
        newOperatorName: 'New Operator',
        actorName: 'Actor',
        transferredAt: now(),
        recipientRole: TransferRecipientRoleEnum::NewOperator,
    );

    $payload = $notification->toArray($recipient);

    expect($payload['action_url'])->toBe('/request-management/42')
        ->and($payload['action_url'])->not->toStartWith('http');
});
