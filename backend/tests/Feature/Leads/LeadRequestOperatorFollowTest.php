<?php

use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\Notifications\RecordAssignmentNotification;
use App\Services\RequestManagement\RequestOperatorWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Task 427463: an Operatore assigned to (or moved on) a lead AFTER its
 * conversion reaches the requests born from it, through every write channel,
 * unless the request was reassigned by hand or is closed.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();

    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("leads.{$ability}");
    }

    $this->actor = User::factory()->create();
    $this->actor->givePermissionTo(['leads.viewAny', 'leads.view', 'leads.update']);
    Sanctum::actingAs($this->actor);
});

/**
 * @param  array<int, array<string, mixed>>  $quotes
 * @return array{0: Lead, 1: array<int, Quote>}
 */
function leadFollowFixture(?int $operatorId, array $quotes = [[]]): array
{
    $lead = Lead::factory()->create(['operator_id' => $operatorId]);
    $opportunity = Opportunity::factory()->create(['lead_id' => $lead->id]);

    return [$lead, array_map(
        static fn (array $attributes): Quote => Quote::factory()->create(['opportunity_id' => $opportunity->id, ...$attributes]),
        $quotes,
    )];
}

function leadFollowSetOperator(Quote $quote, int $operatorId): void
{
    $changed = [];
    $old = [];
    app(RequestOperatorWriter::class)->apply($quote, $operatorId, $changed, $old);
}

it('the lead form: an operator assigned after the conversion reaches the request, team and notification', function () {
    $operator = User::factory()->create();
    [$lead, [$quote]] = leadFollowFixture(null, [['operator_id' => null]]);

    $this->patchJson("/api/leads/{$lead->id}", ['operator_id' => $operator->id])->assertOk();

    $quote->refresh();
    expect($quote->operator_id)->toBe($operator->id)
        ->and($quote->managers()->pluck('users.id')->all())->toContain($operator->id);
    Notification::assertSentTo($operator, RecordAssignmentNotification::class);
});

it('the grid cell: the inline edit of the Operatore reaches the request', function () {
    $operator = User::factory()->create();
    [$lead, [$quote]] = leadFollowFixture(null, [['operator_id' => null]]);

    $this->patchJson("/api/tables/leads/rows/{$lead->id}", ['column' => 'operator', 'value' => $operator->id])->assertOk();

    expect($quote->fresh()->operator_id)->toBe($operator->id);
});

it('the bulk assignment: every converted lead hands its new operator to its requests', function () {
    $operator = User::factory()->create();
    [$first, [$firstQuote]] = leadFollowFixture(null, [['operator_id' => null]]);
    [$second, [$secondQuote]] = leadFollowFixture(null, [['operator_id' => null]]);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$first->id, $second->id],
        'mode' => 'single',
        'operator_id' => $operator->id,
    ])->assertOk();

    expect($firstQuote->fresh()->operator_id)->toBe($operator->id)
        ->and($secondQuote->fresh()->operator_id)->toBe($operator->id);
});

it('a reassignment moves only the open requests still on the lead previous operator', function () {
    $previous = User::factory()->create();
    $byHand = User::factory()->create();
    $next = User::factory()->create();
    $closedStatusId = QuoteWorkflowStatus::factory()->system('closed_won')->create()->id;
    [$lead, [$following, $reassigned, $closed]] = leadFollowFixture($previous->id, [[], [], ['quote_workflow_status_id' => $closedStatusId]]);
    foreach ([$following, $closed] as $quote) {
        leadFollowSetOperator($quote, $previous->id);
    }
    leadFollowSetOperator($reassigned, $byHand->id);

    $this->patchJson("/api/leads/{$lead->id}", ['operator_id' => $next->id])->assertOk();

    expect($following->fresh()->operator_id)->toBe($next->id)
        ->and($reassigned->fresh()->operator_id)->toBe($byHand->id)
        ->and($closed->fresh()->operator_id)->toBe($previous->id);
});

it('clearing the lead operator leaves its requests alone', function () {
    $operator = User::factory()->create();
    [$lead, [$quote]] = leadFollowFixture($operator->id);
    leadFollowSetOperator($quote, $operator->id);

    $this->patchJson("/api/leads/{$lead->id}", ['operator_id' => null])->assertOk();

    expect($quote->fresh()->operator_id)->toBe($operator->id);
});
