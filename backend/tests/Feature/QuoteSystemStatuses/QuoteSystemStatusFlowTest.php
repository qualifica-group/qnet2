<?php

declare(strict_types=1);

use App\Enums\QuoteLineType;
use App\Enums\WorkflowStatusGroup;
use App\Models\Contract;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteSystemStatus;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0404 part A: Offerte reads and writes the system status, Gestione
// Richieste the working status, the two in step (AC-004..AC-008, AC-003b).

uses(RefreshDatabase::class);

function flowSystemStatus(string $behavior): QuoteSystemStatus
{
    return QuoteSystemStatus::query()->where('behavior', $behavior)->orderBy('sort_order')->firstOrFail();
}

function flowQuote(): Quote
{
    $quote = Quote::factory()->create(['opportunity_id' => Opportunity::factory()]);
    QuoteLine::factory()->create(['quote_id' => $quote->id, 'line_type' => QuoteLineType::Revenue]);

    return $quote;
}

function flowActor(): void
{
    $actor = quoteTableUserWith(['viewAny', 'view', 'update']);
    foreach (['notes.create', 'request-management.view', 'request-management.viewAll'] as $permission) {
        $actor->givePermissionTo(Permission::findOrCreate($permission));
    }
    Sanctum::actingAs($actor);
}

it('AC-004: the workflow configurator links each status; the link wins over a sent group', function () {
    Permission::findOrCreate('quote-workflows.update');
    Sanctum::actingAs(User::factory()->create()->givePermissionTo('quote-workflows.update'));
    $provisional = flowSystemStatus('validated');

    $this->putJson('/api/quote-workflows/default-statuses', ['statuses' => [
        ['name' => 'In validazione', 'quote_system_status_id' => $provisional->id, 'group' => 'open'],
        ['name' => 'Solo gruppo', 'group' => 'pending'],
    ]])->assertOk()
        ->assertJsonPath('data.1.quote_system_status.name', 'Vinto provvisorio')
        ->assertJsonPath('data.1.group', 'validated')
        ->assertJsonPath('data.2.quote_system_status.name', 'In attesa');
});

it('AC-005: the Offerte grid filters by system status name', function () {
    Sanctum::actingAs(quoteTableUserWith(['viewAny', 'view']));
    $provisional = flowSystemStatus('validated');
    $working = QuoteWorkflowStatus::factory()->global()->create(['quote_system_status_id' => $provisional->id]);
    $inProvisional = Quote::factory()->create(['quote_workflow_status_id' => $working->id]);
    $open = Quote::factory()->create();

    $ids = collect($this->postJson('/api/tables/quotes/rows', [
        'startRow' => 0, 'endRow' => 10,
        'filterModel' => ['quote_system_status' => ['filterType' => 'set', 'values' => ['Vinto provvisorio']]],
    ])->assertOk()->json('items'))->pluck('id');

    expect($ids->all())->toBe([$inProvisional->id])->and($ids)->not->toContain($open->id);
});

it('AC-006: picking a system status writes the first linked working status, a no-op when already linked, 422 when none', function () {
    flowActor();
    $quote = flowQuote();
    $pending = flowSystemStatus('pending');
    $second = QuoteWorkflowStatus::factory()->global()->create(['quote_system_status_id' => $pending->id, 'sort_order' => 30]);
    $first = QuoteWorkflowStatus::factory()->global()->create(['quote_system_status_id' => $pending->id, 'sort_order' => 20]);

    $this->patchJson("/api/quotes/{$quote->id}", ['quote_system_status_id' => $pending->id])->assertOk()
        ->assertJsonPath('data.quote_system_status.id', $pending->id);
    expect($quote->fresh()->quote_workflow_status_id)->toBe($first->id);

    $quote->forceFill(['quote_workflow_status_id' => $second->id])->saveQuietly();
    $this->patchJson("/api/quotes/{$quote->id}", ['quote_system_status_id' => $pending->id])->assertOk();
    expect($quote->fresh()->quote_workflow_status_id)->toBe($second->id);

    $unlinked = QuoteSystemStatus::query()->create(['name' => 'Mai usato', 'color' => 'slate', 'behavior' => 'pending', 'sort_order' => 25]);
    $this->patchJson("/api/quotes/{$quote->id}", ['quote_system_status_id' => $unlinked->id])
        ->assertUnprocessable()->assertJsonValidationErrors('quote_system_status_id');

    $this->patchJson("/api/quotes/{$quote->id}", ['quote_system_status_id' => $pending->id, 'quote_workflow_status_id' => $first->id])
        ->assertUnprocessable();
});

it('AC-007/A-D7: a working status change elsewhere is the system status Offerte reads; the detail offers the linked ones', function () {
    flowActor();
    $quote = flowQuote();
    $provisional = flowSystemStatus('validated');
    $working = QuoteWorkflowStatus::factory()->global()->create(['quote_system_status_id' => $provisional->id]);
    // Only this working status links it: its note requirement is the option's.
    $budget = QuoteSystemStatus::query()->create(['name' => 'Budget', 'color' => 'amber', 'behavior' => 'pending', 'sort_order' => 15]);
    QuoteWorkflowStatus::factory()->global()->create(['quote_system_status_id' => $budget->id, 'requires_note' => true]);

    $quote->forceFill(['quote_workflow_status_id' => $working->id])->saveQuietly();

    $options = collect($this->getJson("/api/quotes/{$quote->id}")->assertOk()
        ->assertJsonPath('data.quote_system_status.name', 'Vinto provvisorio')
        ->assertJsonPath('data.quote_system_status_id', $provisional->id)
        ->json('data.quote_system_statuses'));

    expect($options->pluck('name')->all())->toContain('Aperto', 'Budget', 'Vinto provvisorio', 'Vinto', 'Perso')
        ->and($options->firstWhere('id', $budget->id)['requires_note'])->toBeTrue();
});

it('AC-008: Vinto from Offerte creates the contract, Vinto provvisorio does not', function () {
    flowActor();
    $provisionalQuote = flowQuote();
    $wonQuote = flowQuote();
    QuoteWorkflowStatus::factory()->global()->create(['quote_system_status_id' => flowSystemStatus('validated')->id]);

    $this->patchJson("/api/quotes/{$provisionalQuote->id}", ['quote_system_status_id' => flowSystemStatus('validated')->id])->assertOk();
    $this->patchJson("/api/quotes/{$wonQuote->id}", ['quote_system_status_id' => flowSystemStatus('closed_won')->id])->assertOk();

    expect(Contract::query()->where('quote_id', $provisionalQuote->id)->exists())->toBeFalse()
        ->and(Contract::query()->where('quote_id', $wonQuote->id)->exists())->toBeTrue();
});

it('AC-003b: a system status added by hand with behavior closed_won behaves like Vinto', function () {
    flowActor();
    $quote = flowQuote();
    $manualWon = QuoteSystemStatus::query()->create(['name' => 'Firmato', 'color' => 'emerald', 'behavior' => 'closed_won', 'sort_order' => 35]);
    $working = QuoteWorkflowStatus::factory()->global()->create(['quote_system_status_id' => $manualWon->id]);

    expect($working->group)->toBe(WorkflowStatusGroup::ClosedWon);

    $this->patchJson("/api/quotes/{$quote->id}", ['quote_system_status_id' => $manualWon->id])->assertOk();

    expect($quote->fresh()->quote_workflow_status_id)->toBe($working->id)
        ->and($quote->fresh()->accepted_at)->not->toBeNull()
        ->and(Contract::query()->where('quote_id', $quote->id)->exists())->toBeTrue();
});
