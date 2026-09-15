<?php

use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\Notifications\RequestTransferredNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0130, AC-007: the three bulk actions of RequestManagementController —
// assign-operators / assign-manager-ga1 / transfer — under `enrollee-management`
// require `enrollee-management.update` + the action's own specific ability,
// ignore ids outside the D-3 perimeter or outside the D-2 status filter (same
// semantics as Gestione Richieste), and the transfer notifies the holders of
// `enrollee-management.receiveTransferNotifications`, never
// `request-management.receiveTransferNotifications`.

uses(RefreshDatabase::class);

if (! function_exists('bulkStatusId')) {
    function bulkStatusId(string $systemKey): int
    {
        return QuoteWorkflowStatus::query()
            ->whereNull('quote_workflow_id')
            ->where('system_key', $systemKey)
            ->value('id')
            ?? QuoteWorkflowStatus::factory()->global()->system($systemKey)->create()->id;
    }
}

if (! function_exists('bulkQuote')) {
    /**
     * @param  array<string, mixed>  $attributes
     */
    function bulkQuote(string $systemKey, array $attributes = []): Quote
    {
        return Quote::factory()->create([
            ...$attributes,
            'quote_workflow_status_id' => bulkStatusId($systemKey),
        ]);
    }
}

if (! function_exists('bulkActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function bulkActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'update', 'viewAll', 'assignOperator', 'assignManagerGa1', 'transferContact', 'receiveTransferNotifications'] as $ability) {
            Permission::findOrCreate("enrollee-management.{$ability}");
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("enrollee-management.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// assign-operators — permission gate, D-2/D-3 scope
// ---------------------------------------------------------------------------

it('assign-operators requires enrollee-management.update AND .assignOperator (403 without either)', function () {
    $withoutAssign = bulkActor(['viewAny', 'viewAll', 'update']);
    $quote = bulkQuote('validated');
    Sanctum::actingAs($withoutAssign);

    $this->postJson('/api/enrollee-management/assign-operators', [
        'request_ids' => [$quote->id],
        'mode' => 'single',
        'operator_id' => $withoutAssign->id,
    ])->assertForbidden();

    $withoutUpdate = bulkActor(['viewAny', 'viewAll', 'assignOperator']);
    Sanctum::actingAs($withoutUpdate);

    $this->postJson('/api/enrollee-management/assign-operators', [
        'request_ids' => [$quote->id],
        'mode' => 'single',
        'operator_id' => $withoutUpdate->id,
    ])->assertForbidden();

    expect($quote->fresh()->operator_id)->toBeNull();
});

it('assign-operators (mode=single) skips a request outside validated/closed_won, even in perimeter', function () {
    $actor = bulkActor(['viewAny', 'viewAll', 'update', 'assignOperator']);
    $site = OperationalSite::factory()->withAddress()->create();
    $operator = User::factory()->create();
    EmploymentProfile::factory()->physicalSite($site)->create(['user_id' => $operator->id]);

    $inScope = bulkQuote('closed_won', ['operational_site_id' => $site->id]);
    $outOfState = bulkQuote('open', ['operational_site_id' => $site->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/enrollee-management/assign-operators', [
        'request_ids' => [$inScope->id, $outOfState->id],
        'mode' => 'single',
        'operator_id' => $operator->id,
    ])->assertOk()->assertJsonPath('data.assigned', 1);

    expect($inScope->fresh()->operator_id)->toBe($operator->id)
        ->and($outOfState->fresh()->operator_id)->toBeNull();
});

it('assign-operators skips a request outside the D-3 perimeter (viewAny alone, another operator\'s row)', function () {
    $actor = bulkActor(['viewAny', 'update', 'assignOperator']);
    // `mode=balanced`, not `single`: the D-3 reachability is what this test
    // checks, and `single`'s own candidate-coverage rule (Sede + competence)
    // is orthogonal to it (covered in RequestManagementAssignSingleOperatorTest
    // for the shared service).
    $mine = bulkQuote('validated', ['operator_id' => $actor->id]);
    $someoneElses = bulkQuote('validated');
    Sanctum::actingAs($actor);

    $this->postJson('/api/enrollee-management/assign-operators', [
        'request_ids' => [$mine->id, $someoneElses->id],
        'mode' => 'balanced',
    ])->assertOk()->assertJsonPath('data.assigned', 0)->assertJsonPath('data.skipped', 1);
});

// ---------------------------------------------------------------------------
// assign-manager-ga1 — permission gate, D-2/D-3 scope
// ---------------------------------------------------------------------------

it('assign-manager-ga1 requires enrollee-management.update AND .assignManagerGa1', function () {
    $actor = bulkActor(['viewAny', 'viewAll', 'update']);
    $quote = bulkQuote('validated');
    Sanctum::actingAs($actor);

    $this->postJson('/api/enrollee-management/assign-manager-ga1', [
        'request_ids' => [$quote->id],
        'manager_ga1_id' => $actor->id,
    ])->assertForbidden();
});

it('assign-manager-ga1 reaches only the requests in D-2/D-3 scope', function () {
    $actor = bulkActor(['viewAny', 'viewAll', 'update', 'assignManagerGa1']);
    $inScope = bulkQuote('closed_won');
    $outOfState = bulkQuote('open');
    Sanctum::actingAs($actor);

    $this->postJson('/api/enrollee-management/assign-manager-ga1', [
        'request_ids' => [$inScope->id, $outOfState->id],
        'manager_ga1_id' => $actor->id,
    ])->assertOk()->assertJsonPath('data.assigned', 1);
});

// ---------------------------------------------------------------------------
// transfer — permission gate, D-2/D-3 scope, notification recipients
// ---------------------------------------------------------------------------

it('transfer requires enrollee-management.update AND .transferContact', function () {
    $actor = bulkActor(['viewAny', 'viewAll', 'update']);
    $destination = OperationalSite::factory()->withAddress()->create();
    $quote = bulkQuote('validated');
    Sanctum::actingAs($actor);

    $this->postJson('/api/enrollee-management/transfer', [
        'request_ids' => [$quote->id],
        'operational_site_id' => $destination->id,
        'operator_id' => $actor->id,
    ])->assertForbidden();

    expect($quote->fresh()->operational_site_id)->toBeNull();
});

it('transfer reaches only requests in D-2/D-3 scope and leaves the rest untouched', function () {
    $actor = bulkActor(['viewAny', 'viewAll', 'update', 'transferContact']);
    $destination = OperationalSite::factory()->withAddress()->create();
    $inScope = bulkQuote('validated');
    $outOfState = bulkQuote('pending');
    Sanctum::actingAs($actor);

    $this->postJson('/api/enrollee-management/transfer', [
        'request_ids' => [$inScope->id, $outOfState->id],
        'operational_site_id' => $destination->id,
        'operator_id' => $actor->id,
    ])->assertOk()->assertJsonPath('data.transferred', 1);

    expect($inScope->fresh()->operational_site_id)->toBe($destination->id)
        ->and($outOfState->fresh()->operational_site_id)->toBeNull();
});

it('transfer notifies holders of enrollee-management.receiveTransferNotifications, never request-management\'s', function () {
    Notification::fake();

    $actor = bulkActor(['viewAny', 'viewAll', 'update', 'transferContact']);
    $enrolleeSupervisor = User::factory()->create();
    $enrolleeSupervisor->givePermissionTo('enrollee-management.receiveTransferNotifications');
    $requestSupervisor = User::factory()->create();
    $requestSupervisor->givePermissionTo('request-management.receiveTransferNotifications');
    $destination = OperationalSite::factory()->withAddress()->create();
    $newOperator = User::factory()->create();
    $quote = bulkQuote('closed_won');
    Sanctum::actingAs($actor);

    $this->postJson('/api/enrollee-management/transfer', [
        'request_ids' => [$quote->id],
        'operational_site_id' => $destination->id,
        'operator_id' => $newOperator->id,
    ])->assertOk()->assertJsonPath('data.transferred', 1);

    Notification::assertSentTo($enrolleeSupervisor, RequestTransferredNotification::class);
    Notification::assertNotSentTo($requestSupervisor, RequestTransferredNotification::class);
});

it('the transfer notification for enrollee-management deep-links to /enrollee-management/{quoteId} for a recipient without opportunities.view (spec 0086 MT-04b: the fallback opens the Offerta, not the Opportunity)', function () {
    Notification::fake();

    $actor = bulkActor(['viewAny', 'viewAll', 'update', 'transferContact']);
    $destination = OperationalSite::factory()->withAddress()->create();
    $newOperator = User::factory()->create();
    $newOperator->givePermissionTo('enrollee-management.view');
    $quote = bulkQuote('closed_won');
    Sanctum::actingAs($actor);

    $this->postJson('/api/enrollee-management/transfer', [
        'request_ids' => [$quote->id],
        'operational_site_id' => $destination->id,
        'operator_id' => $newOperator->id,
    ])->assertOk();

    Notification::assertSentTo(
        $newOperator,
        function (RequestTransferredNotification $notification) use ($newOperator, $quote): bool {
            return $notification->toArray($newOperator)['action_url'] === "/enrollee-management/{$quote->id}";
        },
    );
});
