<?php

use App\Enums\TeamActivityRecordEnum;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\WorkOrder;
use App\Notifications\TeamActivityNotification;
use App\Services\Contracts\ContractLifecycleManager;
use App\Services\Notifications\AssignmentNotifier;
use App\Services\Notifications\TeamActivityNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0413: a team member's new or moved Offerta, Contratto, Commessa reaches
 * the managers above them in the org chart holding `<resource>.notifyTeam`.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (TeamActivityRecordEnum::permissions() as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $this->head = orgChartPerson('Head');
    $this->head->givePermissionTo(TeamActivityRecordEnum::permissions());
    $this->supervisor = orgChartPerson('Supervisor', [], $this->head);
    $this->supervisor->givePermissionTo(TeamActivityRecordEnum::permissions());
    $this->silentManager = orgChartPerson('Silent manager', [], $this->head);
    $this->member = orgChartPerson('Member', [], $this->supervisor, $this->silentManager);
    $this->outsider = orgChartPerson('Outsider');
    $this->outsider->givePermissionTo(TeamActivityRecordEnum::permissions());

    Notification::fake();
});

function otherQuoteStatus(Quote $quote): QuoteWorkflowStatus
{
    return QuoteWorkflowStatus::query()->whereKeyNot($quote->quote_workflow_status_id)->firstOrFail();
}

it('tells every manager above the actor holding the permission, nobody else', function () {
    $this->actingAs($this->member);
    $quote = Quote::factory()->create();
    $previous = $quote->quote_workflow_status_id;
    $quote->forceFill(['quote_workflow_status_id' => otherQuoteStatus($quote)->id])->save();

    app(TeamActivityNotifier::class)->statusChanged($quote->fresh(), $previous);

    Notification::assertSentTo([$this->supervisor, $this->head], TeamActivityNotification::class);
    Notification::assertNotSentTo([$this->silentManager, $this->outsider, $this->member], TeamActivityNotification::class);
});

it('names the actor, the record and both statuses', function () {
    $this->actingAs($this->member);
    $quote = Quote::factory()->create(['title' => 'Corso sicurezza']);
    $previous = QuoteWorkflowStatus::query()->findOrFail($quote->quote_workflow_status_id);
    $next = otherQuoteStatus($quote);
    $quote->forceFill(['quote_workflow_status_id' => $next->id])->save();

    app(TeamActivityNotifier::class)->statusChanged($quote->fresh(), $previous->id);

    Notification::assertSentTo($this->supervisor, TeamActivityNotification::class, function (TeamActivityNotification $notification) use ($previous, $next): bool {
        $message = $notification->toArray($this->supervisor)['message'];

        return str_contains($message, 'Member') && str_contains($message, 'Corso sicurezza')
            && str_contains($message, $previous->name) && str_contains($message, $next->name);
    });
});

it('stays silent when the status did not move, under the mute and with nobody behind the write', function () {
    $quote = Quote::factory()->create();

    $this->actingAs($this->member);
    app(TeamActivityNotifier::class)->statusChanged($quote, $quote->quote_workflow_status_id);
    AssignmentNotifier::muted(fn () => app(TeamActivityNotifier::class)->created($quote));

    auth()->logout();
    app(TeamActivityNotifier::class)->created($quote);

    Notification::assertNothingSent();
});

it('announces a new offer through the quote status hook, a status move otherwise', function () {
    $this->actingAs($this->member);
    $quote = Quote::factory()->create();

    app(ContractLifecycleManager::class)->syncOnStatusChange($quote, null);

    Notification::assertSentTo($this->supervisor, TeamActivityNotification::class, fn (TeamActivityNotification $notification): bool => $notification->toArray($this->supervisor)['title'] === 'Your team created a quote');
});

it('announces a new work order when it gets its first status', function () {
    $this->actingAs($this->member);
    $workOrder = WorkOrder::factory()->create();

    app(TeamActivityNotifier::class)->created($workOrder);

    Notification::assertSentTo($this->supervisor, TeamActivityNotification::class, fn (TeamActivityNotification $notification): bool => $notification->toArray($this->supervisor)['title'] === 'Your team created a work order');
});

it('tells the managers when a team member changes a contract status', function () {
    foreach (['viewAny', 'view', 'changeStatus', 'viewAll'] as $ability) {
        $this->member->givePermissionTo(Permission::findOrCreate("contracts.{$ability}", 'web'));
    }
    $contract = Contract::factory()->create(['contract_status_id' => ContractStatus::where('system_key', 'new')->sole()->id]);
    Sanctum::actingAs($this->member);

    $this->postJson("/api/contracts/{$contract->id}/change-status", [
        'contract_status_id' => ContractStatus::where('name', 'Programmato')->sole()->id,
    ])->assertOk();

    Notification::assertSentTo($this->supervisor, TeamActivityNotification::class, function (TeamActivityNotification $notification): bool {
        $data = $notification->toArray($this->supervisor);

        return $data['title'] === 'A contract of your team changed status' && $data['action_url'] === null;
    });
});
