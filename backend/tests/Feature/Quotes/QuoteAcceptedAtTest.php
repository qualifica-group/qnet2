<?php

declare(strict_types=1);

use App\Enums\QuoteLineType;
use App\Enums\WorkflowStatusGroup;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteWorkflowStatus;
use App\Models\Registry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0365 D-6: `quotes.accepted_at` follows the workflow status on every
 * write channel (Quote `saving` hook -> QuoteAcceptedDateStamper).
 */
uses(RefreshDatabase::class);

function acceptedAtStatus(string $systemKey): QuoteWorkflowStatus
{
    return QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', $systemKey)->sole();
}

function acceptedAtExtraWonStatus(): QuoteWorkflowStatus
{
    return QuoteWorkflowStatus::factory()->global()->create([
        'group' => WorkflowStatusGroup::ClosedWon,
        'requires_note' => false,
        'sort_order' => 99,
    ]);
}

function acceptedAtQuote(array $attributes = []): Quote
{
    $quote = Quote::factory()->create($attributes);
    QuoteLine::factory()->create(['quote_id' => $quote->id, 'line_type' => QuoteLineType::Revenue]);

    return $quote;
}

function acceptedAtToday(): string
{
    return CarbonImmutable::today()->toDateString();
}

it('AC-008: the Offerte service stamps today when the quote becomes won', function () {
    $actor = quoteTableUserWith(['viewAny', 'view', 'update']);
    $quote = acceptedAtQuote();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quotes/{$quote->id}", ['quote_workflow_status_id' => acceptedAtStatus('closed_won')->id])->assertOk();

    expect($quote->fresh()->accepted_at->toDateString())->toBe(acceptedAtToday());
});

it('AC-008: the grid inline edit stamps today when the quote becomes won', function () {
    $actor = quoteTableUserWith(['viewAny', 'view', 'update']);
    $actor->givePermissionTo([
        Permission::findOrCreate('notes.create'),
        Permission::findOrCreate('request-management.view'),
        Permission::findOrCreate('request-management.viewAll'),
    ]);
    $quote = acceptedAtQuote();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/quotes/rows/{$quote->id}", [
        'column' => 'quote_workflow_status',
        'value' => acceptedAtStatus('closed_won')->id,
    ])->assertOk();

    expect($quote->fresh()->accepted_at->toDateString())->toBe(acceptedAtToday());
});

it('AC-008: Gestione Richieste stamps today when the quote becomes won', function () {
    foreach (['viewAny', 'view', 'update', 'viewAll'] as $ability) {
        Permission::findOrCreate("request-management.{$ability}");
    }
    $operator = User::factory()->create();
    $operator->givePermissionTo(['request-management.viewAny', 'request-management.view', 'request-management.update', 'request-management.viewAll']);

    $registry = Registry::factory()->create();
    $registry->personalData()->create([
        'type' => 'individual', 'first_name' => 'Mario', 'last_name' => 'Rossi', 'tax_code' => 'RSSMRA80A01H501U',
    ]);
    $opportunity = Opportunity::factory()->create(['registry_id' => $registry->id]);
    $opportunity->managers()->sync([$operator->id => ['position' => 2]]);
    $quote = acceptedAtQuote(['opportunity_id' => $opportunity->id, 'operator_id' => $operator->id]);
    Sanctum::actingAs($operator);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'quote_workflow_status_id' => acceptedAtStatus('closed_won')->id,
    ])->assertOk();

    expect($quote->fresh()->accepted_at->toDateString())->toBe(acceptedAtToday());
});

it('AC-009: won to another won status keeps the date, leaving won clears it', function () {
    $quote = acceptedAtQuote();
    $quote->quote_workflow_status_id = acceptedAtStatus('closed_won')->id;
    $quote->save();
    $quote->forceFill(['accepted_at' => '2026-01-15'])->saveQuietly();

    $quote->quote_workflow_status_id = acceptedAtExtraWonStatus()->id;
    $quote->save();
    expect($quote->fresh()->accepted_at->toDateString())->toBe('2026-01-15');

    $quote->quote_workflow_status_id = acceptedAtStatus('closed_lost')->id;
    $quote->save();
    expect($quote->fresh()->accepted_at)->toBeNull();
});

it('stamps a quote created directly in a won status and ignores saves that do not touch the status', function () {
    $won = Quote::factory()->create(['quote_workflow_status_id' => acceptedAtStatus('closed_won')->id]);
    expect($won->fresh()->accepted_at->toDateString())->toBe(acceptedAtToday());

    $won->forceFill(['accepted_at' => '2026-02-01'])->saveQuietly();
    $won->title = 'Renamed';
    $won->save();
    expect($won->fresh()->accepted_at->toDateString())->toBe('2026-02-01');

    $open = Quote::factory()->create();
    expect($open->fresh()->accepted_at)->toBeNull();
});
