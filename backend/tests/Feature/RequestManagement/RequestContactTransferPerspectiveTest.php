<?php

use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Role;
use App\Models\User;
use App\Notifications\RequestTransferredNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

// Spec 0081 — one transfer, three audiences, three texts. AC-018 -> AC-025,
// AC-028. The AC-013..AC-017 side of the transfer stays in
// RequestContactTransferNotificationTest.

uses(RefreshDatabase::class);

if (! function_exists('transferPerspectiveActor')) {
    function transferPerspectiveActor(): User
    {
        $user = User::factory()->create();

        foreach (['view', 'update', 'viewAll', 'transferContact'] as $ability) {
            $user->givePermissionTo(Permission::findOrCreate("request-management.{$ability}"));
        }

        return $user;
    }
}

if (! function_exists('transferPerspectiveRequest')) {
    function transferPerspectiveRequest(?User $operator, ?OperationalSite $originSite = null): Quote
    {
        $opportunity = Opportunity::factory()->create(['name' => 'Acme deal']);

        if ($operator !== null) {
            $opportunity->managers()->attach($operator->id, ['position' => Opportunity::OPERATOR_MANAGER_POSITION]);
        }

        return Quote::factory()->for($opportunity)->create([
            'operational_site_id' => $originSite?->id,
            'supervisor_id' => $operator?->id,
        ]);
    }
}

if (! function_exists('transferPerspectivePost')) {
    /**
     * @param  array<int, int>  $requestIds
     */
    function transferPerspectivePost(object $test, array $requestIds, OperationalSite $destination, User $newOperator): void
    {
        $test->postJson('/api/request-management/transfer', [
            'request_ids' => $requestIds,
            'operational_site_id' => $destination->id,
            'operator_id' => $newOperator->id,
        ])->assertOk();
    }
}

// ---------------------------------------------------------------------------
// AC-018 / AC-019 — the operator who LOST the contact
// ---------------------------------------------------------------------------

it('tells the previous operator, on both channels, that the contact is no longer theirs (AC-018)', function () {
    Notification::fake();

    $actor = transferPerspectiveActor();
    $previousOperator = User::factory()->create(['name' => 'Old Operator']);
    $newOperator = User::factory()->create(['name' => 'New Operator']);
    $destination = OperationalSite::factory()->withAddress()->create();
    $request = transferPerspectiveRequest($previousOperator, OperationalSite::factory()->withAddress()->create());
    Sanctum::actingAs($actor);

    transferPerspectivePost($this, [$request->id], $destination, $newOperator);

    Notification::assertSentTo(
        $previousOperator,
        function (RequestTransferredNotification $notification) use ($previousOperator, $actor): bool {
            $message = (string) $notification->toArray($previousOperator)['message'];

            return $notification->via((object) []) === ['database', 'mail']
                && str_contains($message, 'no longer the operator')
                && str_contains($message, 'Acme deal')
                && str_contains($message, 'New Operator')
                && str_contains($message, $actor->name);
        },
    );
});

it('sends no "contact lost" notification when the request had no operator (AC-019)', function () {
    Notification::fake();

    $actor = transferPerspectiveActor();
    $newOperator = User::factory()->create();
    $destination = OperationalSite::factory()->withAddress()->create();
    $request = transferPerspectiveRequest(null);
    Sanctum::actingAs($actor);

    transferPerspectivePost($this, [$request->id], $destination, $newOperator);

    Notification::assertSentToTimes($newOperator, RequestTransferredNotification::class, 1);
    Notification::assertCount(1);
});

// ---------------------------------------------------------------------------
// AC-021 — the recipient set is the PERMISSION, not a role name
// ---------------------------------------------------------------------------

it('copies whoever holds the permission through a role, and nobody once it is revoked (AC-021)', function () {
    Notification::fake();

    $permission = Permission::findOrCreate('request-management.receiveTransferNotifications');
    $role = Role::findOrCreate('supervisor');
    $role->givePermissionTo($permission);

    $actor = transferPerspectiveActor();
    $viaRole = User::factory()->create();
    $viaRole->assignRole($role);
    $newOperator = User::factory()->create();
    $destination = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    transferPerspectivePost($this, [transferPerspectiveRequest(null)->id], $destination, $newOperator);
    Notification::assertSentTo($viaRole, RequestTransferredNotification::class);

    // Same role, grant revoked: the copy stops.
    Notification::fake();
    $role->revokePermissionTo($permission);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    transferPerspectivePost($this, [transferPerspectiveRequest(null)->id], $destination, $newOperator);
    Notification::assertNotSentTo($viaRole, RequestTransferredNotification::class);
});

// ---------------------------------------------------------------------------
// AC-022 / AC-023 — no double sends, no self sends
// ---------------------------------------------------------------------------

it('sends the incoming operator exactly one notification, the assignment one (AC-022)', function () {
    Notification::fake();

    $permission = Permission::findOrCreate('request-management.receiveTransferNotifications');
    $actor = transferPerspectiveActor();
    $newOperator = User::factory()->create();
    // The incoming operator ALSO holds the supervisory grant: still one copy.
    $newOperator->givePermissionTo($permission);
    $destination = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    transferPerspectivePost($this, [transferPerspectiveRequest(null)->id], $destination, $newOperator);

    Notification::assertSentToTimes($newOperator, RequestTransferredNotification::class, 1);
    Notification::assertSentTo($newOperator, function (RequestTransferredNotification $notification) use ($newOperator): bool {
        return str_contains((string) $notification->toArray($newOperator)['message'], 'assigned you');
    });
});

it('never notifies the actor, even holding the grant and being the incoming operator (AC-023)', function () {
    Notification::fake();

    $actor = transferPerspectiveActor();
    $actor->givePermissionTo(Permission::findOrCreate('request-management.receiveTransferNotifications'));
    $destination = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    transferPerspectivePost($this, [transferPerspectiveRequest($actor)->id], $destination, $actor);

    Notification::assertNothingSent();
});

// ---------------------------------------------------------------------------
// AC-024 — nobody holds the grant
// ---------------------------------------------------------------------------

it('transfers fine with no grant holder, notifying only the two operators (AC-024)', function () {
    Notification::fake();

    $actor = transferPerspectiveActor();
    $previousOperator = User::factory()->create();
    $newOperator = User::factory()->create();
    $destination = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    transferPerspectivePost($this, [transferPerspectiveRequest($previousOperator)->id], $destination, $newOperator);

    Notification::assertSentToTimes($previousOperator, RequestTransferredNotification::class, 1);
    Notification::assertSentToTimes($newOperator, RequestTransferredNotification::class, 1);
    Notification::assertCount(2);
});

// ---------------------------------------------------------------------------
// AC-028 — the role lookup is gone, not commented out
// ---------------------------------------------------------------------------

it('resolves the supervisory audience without any role lookup (AC-028)', function () {
    $source = (string) file_get_contents(app_path('Services/RequestManagement/RequestTransferService.php'));

    expect($source)->not->toContain('User::role(')
        ->and($source)->not->toContain('use App\Models\Role;')
        ->and($source)->toContain('request-management.receiveTransferNotifications');
});
