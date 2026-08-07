<?php

use App\Enums\NotificationLevelEnum;
use App\Models\FieldChangeRequest;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Source;
use App\Models\User;
use App\Notifications\FieldChangeRequestedNotification;
use App\Notifications\FieldChangeRequestResolvedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Notification dispatch (spec 0078): AC-024/025/026.
//
// Unlike NoteMentionNotificationTest, this suite CAN use RefreshDatabase:
// FieldChangeRequestCreator/Approver/Rejecter dispatch their notification as
// plain sequential code AFTER DB::transaction() returns rather than via
// DB::afterCommit() (see FieldChangeRequestCreator::handle() docblock),
// which stays observable under RefreshDatabase's own wrapping transaction.

uses(RefreshDatabase::class);

if (! function_exists('fcrNotifEnsurePermissions')) {
    function fcrNotifEnsurePermissions(): void
    {
        foreach (['viewAny', 'view', 'create', 'manage', 'export', 'viewActivity'] as $ability) {
            Permission::findOrCreate("field-change-requests.{$ability}");
        }
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'viewActivity', 'viewAll', 'updateSource'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }
    }
}

if (! function_exists('fcrNotifActorWith')) {
    /**
     * @param  array<int, string>  $fieldChangeAbilities
     * @param  array<int, string>  $requestManagementAbilities
     */
    function fcrNotifActorWith(array $fieldChangeAbilities, array $requestManagementAbilities = ['view', 'viewAll']): User
    {
        fcrNotifEnsurePermissions();

        $user = User::factory()->create();

        foreach ($fieldChangeAbilities as $ability) {
            $user->givePermissionTo("field-change-requests.{$ability}");
        }

        foreach ($requestManagementAbilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-024 / AC-025 — creation notifies every viewAny holder, not the requester
// ---------------------------------------------------------------------------

it('AC-024/AC-025: creating a request notifies every viewAny holder on database+mail, never the requester, never a stranger', function () {
    Notification::fake();

    $requester = fcrNotifActorWith(['create']);
    $viewer = fcrNotifActorWith(['viewAny']);
    $stranger = fcrNotifActorWith([]);
    $opportunity = Opportunity::factory()->create(['source_id' => Source::factory()->create()->id]);
    $quote = Quote::factory()->for($opportunity)->create();
    $newSource = Source::factory()->create();
    Sanctum::actingAs($requester);

    $this->postJson('/api/field-change-requests', [
        'resource' => 'request-management',
        'subject_id' => $quote->id,
        'field' => 'source_id',
        'requested_value' => $newSource->id,
    ])->assertCreated();

    $fieldChangeRequest = FieldChangeRequest::sole();

    Notification::assertSentTo($viewer, function (FieldChangeRequestedNotification $notification) use ($fieldChangeRequest, $viewer): bool {
        $data = $notification->toArray($viewer);

        return $notification->via((object) []) === ['database', 'mail']
            && $data['action_url'] === "/field-change-requests/{$fieldChangeRequest->id}"
            && $data['level'] === NotificationLevelEnum::Info->value;
    });
    Notification::assertNotSentTo($requester, FieldChangeRequestedNotification::class);
    Notification::assertNotSentTo($stranger, FieldChangeRequestedNotification::class);
});

// ---------------------------------------------------------------------------
// AC-026 — resolution notifies the requester with the right level + note
// ---------------------------------------------------------------------------

it('AC-026: approval notifies the requester with level success and the handling note', function () {
    Notification::fake();

    $manager = fcrNotifActorWith(['manage'], ['view', 'viewAll', 'update', 'updateSource']);
    $requester = fcrNotifActorWith(['create']);
    $currentSource = Source::factory()->create();
    $requestedSource = Source::factory()->create();
    $opportunity = Opportunity::factory()->create(['source_id' => $currentSource->id]);
    $quote = Quote::factory()->for($opportunity)->create();
    $fieldChangeRequest = FieldChangeRequest::factory()->create([
        'resource' => 'request-management',
        'subject_type' => 'quote',
        'subject_id' => $quote->id,
        'field' => 'source_id',
        'current_value' => $currentSource->id,
        'requested_value' => $requestedSource->id,
        'status' => 'pending',
        'pending_key' => "quote:{$quote->id}:source_id",
        'requested_by_id' => $requester->id,
    ]);
    Sanctum::actingAs($manager);

    $this->postJson("/api/field-change-requests/{$fieldChangeRequest->id}/approve", ['note' => 'Confermato con il cliente.'])
        ->assertOk();

    Notification::assertSentTo($requester, function (FieldChangeRequestResolvedNotification $notification) use ($requester): bool {
        $data = $notification->toArray($requester);

        return $notification->via((object) []) === ['database', 'mail']
            && $data['level'] === NotificationLevelEnum::Success->value
            && str_contains((string) $data['message'], 'Confermato con il cliente.');
    });
});

it('AC-026: rejection notifies the requester with level warning', function () {
    Notification::fake();

    $manager = fcrNotifActorWith(['manage'], ['view', 'viewAll', 'update', 'updateSource']);
    $requester = fcrNotifActorWith(['create']);
    $opportunity = Opportunity::factory()->create(['source_id' => Source::factory()->create()->id]);
    $quote = Quote::factory()->for($opportunity)->create();
    $fieldChangeRequest = FieldChangeRequest::factory()->create([
        'resource' => 'request-management',
        'subject_type' => 'quote',
        'subject_id' => $quote->id,
        'field' => 'source_id',
        'current_value' => $opportunity->source_id,
        'requested_value' => Source::factory()->create()->id,
        'status' => 'pending',
        'pending_key' => "quote:{$quote->id}:source_id",
        'requested_by_id' => $requester->id,
    ]);
    Sanctum::actingAs($manager);

    $this->postJson("/api/field-change-requests/{$fieldChangeRequest->id}/reject")->assertOk();

    Notification::assertSentTo($requester, function (FieldChangeRequestResolvedNotification $notification) use ($requester): bool {
        $data = $notification->toArray($requester);

        return $data['level'] === NotificationLevelEnum::Warning->value;
    });
});
