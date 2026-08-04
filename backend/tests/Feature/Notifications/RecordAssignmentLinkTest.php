<?php

use App\Enums\AssignmentRoleEnum;
use App\Enums\AssignmentTargetEnum;
use App\Enums\TransferRecipientRoleEnum;
use App\Models\Registry;
use App\Models\User;
use App\Notifications\RecordAssignmentNotification;
use App\Notifications\RequestTransferredNotification;
use App\Services\Notifications\AssignmentNotifier;
use App\Support\Notifications\RecordLinkResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;

// Spec 0081 — the per-recipient deep link, the no-access fallback, the
// after-commit contract and the i18n coverage. AC-012 -> AC-017, AC-026,
// AC-027.

uses(RefreshDatabase::class);

if (! function_exists('linkUserWith')) {
    /**
     * @param  array<int, string>  $permissions
     */
    function linkUserWith(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission));
        }

        return $user;
    }
}

if (! function_exists('assignmentNotificationFor')) {
    function assignmentNotificationFor(AssignmentTargetEnum $target): RecordAssignmentNotification
    {
        return new RecordAssignmentNotification(
            target: $target,
            role: AssignmentRoleEnum::Manager,
            recordId: 7,
            recordLabel: 'Acme deal',
            position: 2,
            actorName: 'Actor',
        );
    }
}

// ---------------------------------------------------------------------------
// AC-012 — nothing is sent when the write rolls back
// ---------------------------------------------------------------------------

it('a rolled back write sends nothing (AC-012)', function () {
    Notification::fake();

    $actor = User::factory()->create();
    $manager = User::factory()->create();
    $registry = Registry::factory()->create();

    try {
        DB::transaction(function () use ($registry, $actor, $manager): void {
            app(AssignmentNotifier::class)->notify(
                $registry,
                $actor,
                null,
                [$manager->id => 1],
            );

            throw new RuntimeException('rolled back on purpose');
        });
    } catch (RuntimeException) {
        // expected
    }

    Notification::assertNothingSent();
});

// ---------------------------------------------------------------------------
// AC-013 / AC-014 / AC-016 — the resolution ladder
// ---------------------------------------------------------------------------

it('points a recipient who may see opportunities at the opportunities module (AC-013)', function () {
    $recipient = linkUserWith(['opportunities.view', 'request-management.view']);

    expect(RecordLinkResolver::pathFor($recipient, AssignmentTargetEnum::Opportunity, 7))
        ->toBe('/opportunities/7');
});

it('falls back to request management for a recipient who may only see that module (AC-014)', function () {
    $recipient = linkUserWith(['request-management.view']);

    expect(RecordLinkResolver::pathFor($recipient, AssignmentTargetEnum::Opportunity, 7))
        ->toBe('/request-management/7');
});

it('points an anagrafica notification at the registries module, or nowhere (AC-016)', function () {
    $withAccess = linkUserWith(['registries.view']);
    $withoutAccess = User::factory()->create();

    expect(RecordLinkResolver::pathFor($withAccess, AssignmentTargetEnum::Registry, 7))->toBe('/registries/7')
        ->and(RecordLinkResolver::pathFor($withoutAccess, AssignmentTargetEnum::Registry, 7))->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-015 — no reachable module: no link, no button, an explanatory sentence
// ---------------------------------------------------------------------------

it('gives a recipient with no module access no link, no mail button and an explanation (AC-015)', function () {
    $recipient = User::factory()->create();
    $notification = assignmentNotificationFor(AssignmentTargetEnum::Opportunity);

    $payload = $notification->toArray($recipient);
    $mail = $notification->toMail($recipient);

    expect($payload['action_url'])->toBeNull()
        ->and($payload['message'])->toContain('ask an administrator')
        ->and($mail->actionUrl)->toBeNull();
});

it('gives the same treatment to the transfer notification (AC-015)', function () {
    $recipient = User::factory()->create();

    $notification = new RequestTransferredNotification(
        requestId: 7,
        contactLabel: 'Acme deal',
        originSiteLabel: null,
        destinationSiteLabel: 'Sede - Napoli',
        previousOperatorName: null,
        newOperatorName: 'New Operator',
        actorName: 'Actor',
        transferredAt: now(),
        recipientRole: TransferRecipientRoleEnum::NewOperator,
    );

    expect($notification->toArray($recipient)['action_url'])->toBeNull()
        ->and($notification->toMail($recipient)->actionUrl)->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-017 — a stored action_url is always an internal path
// ---------------------------------------------------------------------------

it('never persists an absolute URL in action_url (AC-017)', function () {
    $recipient = linkUserWith(['registries.view']);

    $payload = (new RecordAssignmentNotification(
        target: AssignmentTargetEnum::Registry,
        role: AssignmentRoleEnum::Supervisor,
        recordId: 7,
        recordLabel: 'Acme',
        position: null,
        actorName: 'Actor',
    ))->toArray($recipient);

    expect($payload['action_url'])->toBe('/registries/7')
        ->and($payload['action_url'])->not->toStartWith('http');
});

// ---------------------------------------------------------------------------
// AC-026 / AC-027 — i18n coverage and queueing
// ---------------------------------------------------------------------------

it('translates every string the two notifications emit (AC-026)', function () {
    /** @var array<string, string> $italian */
    $italian = json_decode((string) file_get_contents(lang_path('it.json')), true);

    $keys = [
        'Hello :name',
        'View',
        'The system',
        'You cannot open this record: ask an administrator to grant you access to the module.',
        'You were assigned as Supervisor',
        'You were assigned as Account Manager',
        ':actor assigned you as Supervisor on :label.',
        ':actor assigned you as Account Manager :position on :label.',
        'Contact transferred',
        'Contact no longer assigned to you',
        'New contact assigned to you',
    ];

    foreach ($keys as $key) {
        expect($italian)->toHaveKey($key);
    }
});

it('queues both notifications (AC-027)', function () {
    expect(assignmentNotificationFor(AssignmentTargetEnum::Registry))->toBeInstanceOf(ShouldQueue::class);
});
