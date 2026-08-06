<?php

use App\Enums\WorkflowStatusGroup;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Opportunity;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * The Quote -> Contract lifecycle automation (spec 0072, BR-1), exercised
 * end-to-end through the real POST/PATCH /api/quotes endpoints (the hook
 * lives inside QuoteService::create()/update()'s own transaction). AC-008
 * (cascade delete) is already covered by MT-00's ContractStatusSeedTest, not
 * duplicated here.
 */
uses(RefreshDatabase::class);

if (! function_exists('contractLifecycleUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function contractLifecycleUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("quotes.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("quotes.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-001/003 — the create branch
// ---------------------------------------------------------------------------

it('AC-001: a quote status transition into closed_won creates a contract with the default status', function () {
    $openStatus = QuoteWorkflowStatus::factory()->global()->create(['group' => WorkflowStatusGroup::Open]);
    $closedWonStatus = QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'closed_won')->sole();
    $opportunity = Opportunity::factory()->create();
    $actor = contractLifecycleUserWith(['create', 'update']);
    Sanctum::actingAs($actor);

    $quoteId = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'quote_workflow_status_id' => $openStatus->id,
    ])->assertCreated()->json('data.id');

    expect(Contract::where('quote_id', $quoteId)->exists())->toBeFalse();

    $this->patchJson("/api/quotes/{$quoteId}", ['quote_workflow_status_id' => $closedWonStatus->id])->assertOk();

    $defaultContractStatus = ContractStatus::where('is_default', true)->sole();
    $contract = Contract::where('quote_id', $quoteId)->first();

    expect($contract)->not->toBeNull()
        ->and($contract->contract_status_id)->toBe($defaultContractStatus->id)
        ->and($contract->accepted_at->toDateString())->toBe(now()->toDateString());
});

it('AC-003: a quote created directly in closed_won already has its contract row, in the same request', function () {
    $closedWonStatus = QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'closed_won')->sole();
    $opportunity = Opportunity::factory()->create();
    $actor = contractLifecycleUserWith(['create']);
    Sanctum::actingAs($actor);

    $quoteId = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'quote_workflow_status_id' => $closedWonStatus->id,
    ])->assertCreated()->json('data.id');

    expect(Contract::where('quote_id', $quoteId)->exists())->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-002 — idempotent re-entry
// ---------------------------------------------------------------------------

it('AC-002: re-entering closed_won a second time does not duplicate the contract nor rewrite accepted_at', function () {
    $openStatus = QuoteWorkflowStatus::factory()->global()->create(['group' => WorkflowStatusGroup::Open]);
    $closedWonStatus = QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'closed_won')->sole();
    $opportunity = Opportunity::factory()->create();
    $actor = contractLifecycleUserWith(['create', 'update']);
    Sanctum::actingAs($actor);

    $quoteId = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'quote_workflow_status_id' => $openStatus->id,
    ])->assertCreated()->json('data.id');

    $this->patchJson("/api/quotes/{$quoteId}", ['quote_workflow_status_id' => $closedWonStatus->id])->assertOk();
    $firstAcceptedAt = Contract::where('quote_id', $quoteId)->sole()->accepted_at->toDateString();

    $this->patchJson("/api/quotes/{$quoteId}", ['quote_workflow_status_id' => $openStatus->id])->assertOk();
    $this->patchJson("/api/quotes/{$quoteId}", ['quote_workflow_status_id' => $closedWonStatus->id])->assertOk();

    expect(Contract::where('quote_id', $quoteId)->count())->toBe(1)
        ->and(Contract::where('quote_id', $quoteId)->sole()->accepted_at->toDateString())->toBe($firstAcceptedAt);
});

// ---------------------------------------------------------------------------
// AC-004 — the suspend branch
// ---------------------------------------------------------------------------

it('AC-004: leaving closed_won suspends the contract and remembers its prior status, nothing else changes', function () {
    $closedWonStatus = QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'closed_won')->sole();
    $openStatus = QuoteWorkflowStatus::factory()->global()->create(['group' => WorkflowStatusGroup::Open]);
    $opportunity = Opportunity::factory()->create();
    $actor = contractLifecycleUserWith(['create', 'update']);
    Sanctum::actingAs($actor);

    $quoteId = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'quote_workflow_status_id' => $closedWonStatus->id,
    ])->assertCreated()->json('data.id');

    $contract = Contract::where('quote_id', $quoteId)->sole();
    $scheduledStatus = ContractStatus::where('name', 'Programmato')->sole();
    $contract->forceFill(['contract_status_id' => $scheduledStatus->id, 'payment_notes' => 'Keep me'])->save();

    $this->patchJson("/api/quotes/{$quoteId}", ['quote_workflow_status_id' => $openStatus->id])->assertOk();

    $contract->refresh();
    $suspendedStatus = ContractStatus::where('system_key', 'suspended')->sole();

    expect($contract->contract_status_id)->toBe($suspendedStatus->id)
        ->and($contract->status_before_suspension_id)->toBe($scheduledStatus->id)
        ->and($contract->suspended_at)->not->toBeNull()
        ->and($contract->payment_notes)->toBe('Keep me');
});

// ---------------------------------------------------------------------------
// AC-005 — re-entering closed_won while suspended does NOT auto-reactivate
// ---------------------------------------------------------------------------

it('AC-005: a suspended contract stays suspended when its quote re-enters closed_won', function () {
    $closedWonStatus = QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'closed_won')->sole();
    $openStatus = QuoteWorkflowStatus::factory()->global()->create(['group' => WorkflowStatusGroup::Open]);
    $opportunity = Opportunity::factory()->create();
    $actor = contractLifecycleUserWith(['create', 'update']);
    Sanctum::actingAs($actor);

    $quoteId = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'quote_workflow_status_id' => $closedWonStatus->id,
    ])->assertCreated()->json('data.id');

    $this->patchJson("/api/quotes/{$quoteId}", ['quote_workflow_status_id' => $openStatus->id])->assertOk();
    $this->patchJson("/api/quotes/{$quoteId}", ['quote_workflow_status_id' => $closedWonStatus->id])->assertOk();

    $contract = Contract::where('quote_id', $quoteId)->sole();

    expect($contract->isSuspended())->toBeTrue();
});
