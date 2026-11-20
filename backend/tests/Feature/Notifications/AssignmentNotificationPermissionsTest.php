<?php

use App\Enums\AssignmentRoleEnum;
use App\Enums\AssignmentTargetEnum;
use App\Models\Opportunity;
use App\Models\Role;
use App\Models\User;
use App\Notifications\RecordAssignmentNotification;
use App\Services\Notifications\AssignmentNotifier;
use App\Support\Notifications\AssignmentNotificationChannels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;

// Spec 0420 — the role decides whether an assignment on an Opportunita' or an
// Offerta reaches the bell and/or the email. AC-001 -> AC-007.

uses(RefreshDatabase::class);

if (! function_exists('assignmentPermissionUser')) {
    /**
     * A user whose ONE role holds $permissions.
     *
     * @param  array<int, string>  $permissions
     */
    function assignmentPermissionUser(array $permissions, bool $emails = true): User
    {
        $role = Role::findOrCreate('notification-role-'.uniqid());
        $role->givePermissionTo(array_map(static fn (string $name): Permission => Permission::findOrCreate($name), $permissions));
        $user = User::factory()->create(['email_notifications_enabled' => $emails]);
        $user->assignRole($role);

        return $user;
    }
}

if (! function_exists('assignmentChannels')) {
    /**
     * @return array<int, string>
     */
    function assignmentChannels(AssignmentTargetEnum $target, User $user): array
    {
        return (new RecordAssignmentNotification($target, AssignmentRoleEnum::Manager, 1, 'Label', 'Actor'))->via($user);
    }
}

it('sends bell and email when the role holds both opportunity permissions (AC-001)', function () {
    Notification::fake();
    $manager = assignmentPermissionUser(['opportunities.notifyAssignment', 'opportunities.notifyAssignmentEmail']);

    app(AssignmentNotifier::class)->notify(Opportunity::factory()->create(), null, null, [$manager->id => 1]);

    Notification::assertSentTo(
        $manager,
        RecordAssignmentNotification::class,
        fn (RecordAssignmentNotification $notification, array $channels): bool => $channels === ['database', 'mail'],
    );
});

it('sends only the channel the role grants (AC-002)', function (string $permission, array $channels) {
    expect(assignmentChannels(AssignmentTargetEnum::Opportunity, assignmentPermissionUser([$permission])))->toBe($channels);
})->with([
    'bell only' => ['opportunities.notifyAssignment', ['database']],
    'email only' => ['opportunities.notifyAssignmentEmail', ['mail']],
]);

it('sends nothing when the role holds neither permission (AC-003)', function () {
    Notification::fake();
    $manager = assignmentPermissionUser(['opportunities.view']);

    app(AssignmentNotifier::class)->notify(Opportunity::factory()->create(), null, null, [$manager->id => 1]);

    Notification::assertNothingSentTo($manager);
});

it('reads the opportunity permissions for an opportunity, not the offer ones', function () {
    $manager = assignmentPermissionUser(['quotes.notifyAssignment', 'quotes.notifyAssignmentEmail']);

    expect(assignmentChannels(AssignmentTargetEnum::Opportunity, $manager))->toBe([]);
});

it('keeps the personal email switch on top of the role (AC-004)', function () {
    $manager = assignmentPermissionUser(['opportunities.notifyAssignment', 'opportunities.notifyAssignmentEmail'], emails: false);

    expect(assignmentChannels(AssignmentTargetEnum::Opportunity, $manager))->toBe(['database']);
});

it('lets any of the offer modules grant an offer assignment (AC-005)', function (string $resource) {
    $manager = assignmentPermissionUser(["{$resource}.notifyAssignment", "{$resource}.notifyAssignmentEmail"]);

    expect(assignmentChannels(AssignmentTargetEnum::Quote, $manager))->toBe(['database', 'mail']);
})->with(['quotes', 'request-management', 'enrollee-management']);

it('keeps registries and work orders on bell plus the personal switch (AC-006)', function (AssignmentTargetEnum $target) {
    $user = User::factory()->create();

    expect(assignmentChannels($target, $user))->toBe(['database', 'mail']);
})->with([AssignmentTargetEnum::Registry, AssignmentTargetEnum::WorkOrder]);

it('always notifies the super-admin', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('super-admin'));

    expect(assignmentChannels(AssignmentTargetEnum::Quote, $user))->toBe(['database', 'mail']);
});

it('grants every assignment-notification permission to every operational role, not to add-on ones (AC-007)', function () {
    $seesOpportunities = Role::findOrCreate('sees-opportunities');
    $seesOpportunities->givePermissionTo(Permission::findOrCreate('opportunities.view'));
    $seesWorkOrders = Role::findOrCreate('sees-work-orders');
    $seesWorkOrders->givePermissionTo(Permission::findOrCreate('work-orders.view'));
    $addOn = Role::findOrCreate('import-only');
    $addOn->givePermissionTo(Permission::findOrCreate('request-management.import'));

    (require database_path('migrations/2026_12_21_100000_add_assignment_notification_permissions.php'))->up();

    foreach ([$seesOpportunities, $seesWorkOrders] as $role) {
        expect($role->fresh()->getPermissionNames()->all())->toContain(...AssignmentNotificationChannels::permissions());
    }

    expect($addOn->fresh()->getPermissionNames()->all())->toBe(['request-management.import']);
});
