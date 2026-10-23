<?php

use App\Enums\CommissionRecalculationMode;
use App\Enums\CommissionRecipientRole;
use App\Enums\WorkflowStatusGroup;
use App\Jobs\RecalculateCommissionConfigurationQuotesJob;
use App\Models\QuoteWorkflowStatus;
use App\Services\Commissions\CommissionRecalculationImpact;
use App\Services\Commissions\QuoteCommissionRecalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

/**
 * Spec 0308, rule channel: GET quote-impact and POST recalculate-quotes
 * (D-6, D-7, D-8).
 */
uses(RefreshDatabase::class);

const RECALC_RULE_PERMISSIONS = ['commission-configurations.view', 'commission-configurations.update', 'quotes.update'];

/** A rule now at 8% used (at 5%) by one quote per status group; returns the rule and the quotes by group. */
function recalcRuleScenario(): array
{
    $product = recalcProduct();
    $rule = recalcRule($product, CommissionRecipientRole::Commercial, 8);
    $quotes = [];

    foreach ([WorkflowStatusGroup::Open, WorkflowStatusGroup::Pending, WorkflowStatusGroup::Validated, WorkflowStatusGroup::ClosedWon] as $group) {
        [$quote, $line] = recalcQuote($product, $group);
        recalcCommission($line, CommissionRecipientRole::Commercial, 5, $rule);
        $quotes[$group->value] = [$quote, $line];
    }

    return [$rule, $quotes];
}

it('AC-008: quote-impact counts only open-group quotes and rule-driven commissions, can_apply needs quotes.update', function () {
    [$rule, $quotes] = recalcRuleScenario();
    // A manual commission on a touched line counts as manual; a line carrying only a manual one is not touched.
    recalcCommission($quotes['open'][1], CommissionRecipientRole::Reporter, 1);
    [, $manualOnlyLine] = recalcQuote($quotes['open'][1]->product);
    recalcCommission($manualOnlyLine, CommissionRecipientRole::Commercial, 4);

    Sanctum::actingAs(recalcActor(RECALC_RULE_PERMISSIONS));
    $this->getJson("/api/commission-configurations/{$rule->id}/quote-impact")
        ->assertOk()
        ->assertJsonPath('data', ['quotes_count' => 3, 'lines_count' => 3, 'manual_commissions_count' => 1, 'can_apply' => true]);

    Sanctum::actingAs(recalcActor(['commission-configurations.view', 'commission-configurations.update']));
    $this->getJson("/api/commission-configurations/{$rule->id}/quote-impact")->assertOk()->assertJsonPath('data.can_apply', false);

    Sanctum::actingAs(recalcActor([]));
    $this->getJson("/api/commission-configurations/{$rule->id}/quote-impact")->assertForbidden();
});

it('AC-009: recalculate-quotes answers 202, realigns only the open quotes and notifies the author', function () {
    [$rule, $quotes] = recalcRuleScenario();
    $actor = recalcActor(RECALC_RULE_PERMISSIONS);
    Sanctum::actingAs($actor);

    $this->postJson("/api/commission-configurations/{$rule->id}/recalculate-quotes", ['mode' => 'KEEP_MANUAL'])
        ->assertStatus(202)
        ->assertJsonPath('message', __('commission_configurations.recalculation_queued'))
        ->assertJsonPath('data.quotes_count', 3);

    foreach (['open', 'pending', 'validated'] as $group) {
        expect((float) $quotes[$group][1]->commissions()->sole()->value)->toBe(8.0);
    }
    expect((float) $quotes['closed_won'][1]->commissions()->sole()->value)->toBe(5.0);

    $notification = $actor->notifications()->sole()->data;
    expect($notification['title'])->toBe(__('commission_configurations.recalculation.title'))
        ->and($notification['message'])->toBe(__('commission_configurations.recalculation.message', ['updated' => 3, 'failed' => 0]))
        ->and($notification['level'])->toBe('success')
        ->and($notification['action_url'])->toBe("/commission-configurations/{$rule->id}");
});

it('AC-010: a quote that fails is skipped and left unchanged, the others are updated and the notification warns', function () {
    [$rule, $quotes] = recalcRuleScenario();
    // A manual commission pointing at a recipient that no longer exists makes KEEP_MANUAL fail for that quote.
    recalcCommission($quotes['pending'][1], CommissionRecipientRole::Reporter, 1, null, ['recipient_id' => 999999]);
    $actor = recalcActor(RECALC_RULE_PERMISSIONS);
    Sanctum::actingAs($actor);

    $this->postJson("/api/commission-configurations/{$rule->id}/recalculate-quotes", ['mode' => 'KEEP_MANUAL'])->assertStatus(202);

    expect((float) $quotes['open'][1]->commissions()->sole()->value)->toBe(8.0)
        ->and((float) $quotes['validated'][1]->commissions()->sole()->value)->toBe(8.0)
        ->and((float) $quotes['pending'][1]->commissions()->where('recipient_role', CommissionRecipientRole::Commercial)->sole()->value)->toBe(5.0);

    $notification = $actor->notifications()->sole()->data;
    expect($notification['level'])->toBe('warning')
        ->and($notification['message'])->toBe(__('commission_configurations.recalculation.message', ['updated' => 2, 'failed' => 1]));
});

it('AC-011: without update on the rule or quotes.update it is 403 and no job is queued; a bad mode is 422', function () {
    [$rule] = recalcRuleScenario();
    Queue::fake();

    Sanctum::actingAs(recalcActor(['commission-configurations.view', 'quotes.update']));
    $this->postJson("/api/commission-configurations/{$rule->id}/recalculate-quotes", ['mode' => 'KEEP_MANUAL'])->assertForbidden();

    Sanctum::actingAs(recalcActor(['commission-configurations.view', 'commission-configurations.update']));
    $this->postJson("/api/commission-configurations/{$rule->id}/recalculate-quotes", ['mode' => 'KEEP_MANUAL'])->assertForbidden();

    Sanctum::actingAs(recalcActor(RECALC_RULE_PERMISSIONS));
    $this->postJson("/api/commission-configurations/{$rule->id}/recalculate-quotes", ['mode' => 'ALL'])->assertUnprocessable()->assertJsonValidationErrors('mode');

    Queue::assertNotPushed(RecalculateCommissionConfigurationQuotesJob::class);
});

it('AC-009: a quote closed after the selection and before the recalculation is skipped and left unchanged', function () {
    [$rule, $quotes] = recalcRuleScenario();
    [$quote, $line] = $quotes['open'];
    $lineIds = app(CommissionRecalculationImpact::class)->touchedLineIdsByQuote($rule)[$quote->id];

    $quote->forceFill(['quote_workflow_status_id' => QuoteWorkflowStatus::factory()->global()->create(['group' => WorkflowStatusGroup::ClosedLost])->id])->save();

    $result = app(QuoteCommissionRecalculator::class)->recalculate($quote->fresh(), CommissionRecalculationMode::RegenerateAll, $lineIds);

    expect($result)->toBeFalse()
        ->and((float) $line->commissions()->sole()->value)->toBe(5.0);
});
