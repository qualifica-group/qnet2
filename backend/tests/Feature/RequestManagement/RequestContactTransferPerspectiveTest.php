<?php

use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Role;
use App\Models\User;
use App\Notifications\RequestTransferredNotification;
use App\Services\RequestManagement\RequestTransferService;
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
            'operator_id' => $operator?->id,
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

            return $notification->via($previousOperator) === ['database', 'mail']
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
// AC-021 — the recipient set is the dedicated ROLE, not `viewAll`
// (spec 0081 rev. 2026-11-12, task 427848: it replaces `{module}.viewAll`)
// ---------------------------------------------------------------------------

it('copies the holders of the dedicated role, never a viewAll holder without it, and nobody once it is removed (AC-021)', function () {
    Notification::fake();

    $role = Role::findOrCreate(RequestTransferService::NOTIFIED_ROLE, 'web');
    $actor = transferPerspectiveActor();
    $holder = User::factory()->create()->assignRole($role);
    $seesEverything = User::factory()->create();
    $seesEverything->givePermissionTo(Permission::findOrCreate('request-management.viewAll'));
    $newOperator = User::factory()->create();
    $destination = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    transferPerspectivePost($this, [transferPerspectiveRequest(null)->id], $destination, $newOperator);
    Notification::assertSentTo($holder, RequestTransferredNotification::class);
    Notification::assertNotSentTo($seesEverything, RequestTransferredNotification::class);

    // Role removed: the copy stops.
    Notification::fake();
    $holder->removeRole($role);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    transferPerspectivePost($this, [transferPerspectiveRequest(null)->id], $destination, $newOperator);
    Notification::assertNotSentTo($holder, RequestTransferredNotification::class);
});

it('does not notify an uninvolved user without the role, even one working the module per Sede', function () {
    Notification::fake();

    $actor = transferPerspectiveActor();
    $bystander = User::factory()->create();

    foreach (['viewAny', 'view', 'update', 'viewSite', 'transferContact'] as $ability) {
        $bystander->givePermissionTo(Permission::findOrCreate("request-management.{$ability}"));
    }

    $previousOperator = User::factory()->create();
    $newOperator = User::factory()->create();
    $destination = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    transferPerspectivePost($this, [transferPerspectiveRequest($previousOperator)->id], $destination, $newOperator);

    Notification::assertNotSentTo($bystander, RequestTransferredNotification::class);
    Notification::assertCount(2);
});

// ---------------------------------------------------------------------------
// AC-022 / AC-023 — no double sends, no self sends
// ---------------------------------------------------------------------------

it('sends the incoming operator exactly one notification, the assignment one (AC-022)', function () {
    Notification::fake();

    $actor = transferPerspectiveActor();
    $newOperator = User::factory()->create();
    // The incoming operator ALSO holds the supervisory role: still one copy.
    $newOperator->assignRole(Role::findOrCreate(RequestTransferService::NOTIFIED_ROLE, 'web'));
    $destination = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    transferPerspectivePost($this, [transferPerspectiveRequest(null)->id], $destination, $newOperator);

    Notification::assertSentToTimes($newOperator, RequestTransferredNotification::class, 1);
    Notification::assertSentTo($newOperator, function (RequestTransferredNotification $notification) use ($newOperator): bool {
        return str_contains((string) $notification->toArray($newOperator)['message'], 'assigned you');
    });
});

it('never notifies the actor, even holding the role and being the incoming operator (AC-023)', function () {
    Notification::fake();

    $actor = transferPerspectiveActor()->assignRole(Role::findOrCreate(RequestTransferService::NOTIFIED_ROLE, 'web'));
    $destination = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    transferPerspectivePost($this, [transferPerspectiveRequest($actor)->id], $destination, $actor);

    Notification::assertNothingSent();
});

// ---------------------------------------------------------------------------
// AC-024 — nobody holds the role
// ---------------------------------------------------------------------------

it('transfers fine with no role holder, notifying only the two operators (AC-024)', function () {
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
// AC-028 — the audience is the dedicated role, the viewAll lookup is gone
// ---------------------------------------------------------------------------

// Spec 0081 rev. 2026-11-12 REQUIREMENT CHANGE (declared, not test tampering,
// CORE §2): AC-028 used to forbid any role lookup because the audience was a
// permission (`{module}.viewAll`, rev. 2026-09-29). The client now picks the
// recipients by name, and admin/super-admin receive every permission by sync,
// so the audience is the dedicated role and the `viewAll` lookup must be gone.
it('resolves the supervisory audience from the dedicated role only (AC-028)', function () {
    $source = (string) file_get_contents(app_path('Services/RequestManagement/RequestTransferService.php'));

    expect($source)->toContain('User::role(self::NOTIFIED_ROLE)')
        ->and($source)->not->toContain('SUPERVISORY_ABILITY')
        ->and($source)->not->toContain('User::permission(');
});
