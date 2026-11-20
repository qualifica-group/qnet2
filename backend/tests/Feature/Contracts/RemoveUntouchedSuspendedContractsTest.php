<?php

declare(strict_types=1);

use App\Enums\StatusSystemKey;
use App\Models\Contract;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Services\Contracts\ContractLifecycleManager;
use App\Services\Contracts\ContractStatusResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

/**
 * Spec 0072 D-3 rev.: the one-off cleanup of the contracts suspended before
 * the revision, on offers no longer won and never worked on.
 */
uses(RefreshDatabase::class);

if (! function_exists('suspendedContractOn')) {
    /** @param  array<string, mixed>  $attributes */
    function suspendedContractOn(Quote $quote, array $attributes = []): Contract
    {
        $resolver = app(ContractStatusResolver::class);

        return Contract::factory()->create([
            'quote_id' => $quote->id,
            'contract_status_id' => $resolver->systemId(StatusSystemKey::Suspended),
            'status_before_suspension_id' => $resolver->defaultActiveId(),
            'suspended_at' => now(),
            ...$attributes,
        ]);
    }
}

it('removes the untouched suspended contracts of offers no longer won, keeping the worked-on ones', function () {
    $untouched = suspendedContractOn(Quote::factory()->create());
    $workedOn = suspendedContractOn(Quote::factory()->create(), ['payment_notes' => 'Bonifico']);
    $wonQuote = Quote::factory()->create([
        'quote_workflow_status_id' => QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'closed_won')->value('id'),
    ]);
    $onWonOffer = suspendedContractOn($wonQuote);

    $this->artisan('contracts:remove-untouched-suspended')
        ->expectsOutputToContain('1 contract(s) removed, 1 worked-on contract(s) kept suspended.')
        ->assertSuccessful();

    expect(Contract::find($untouched->id))->toBeNull()
        ->and(Contract::find($workedOn->id))->not->toBeNull()
        ->and(Contract::find($onWonOffer->id))->not->toBeNull()
        ->and(Activity::where('subject_type', 'quote')->where('subject_id', $untouched->quote_id)
            ->where('event', ContractLifecycleManager::CONTRACT_REMOVED_EVENT)->exists())->toBeTrue();
});

it('changes nothing on a dry run', function () {
    $untouched = suspendedContractOn(Quote::factory()->create());

    $this->artisan('contracts:remove-untouched-suspended', ['--dry-run' => true])
        ->expectsOutputToContain('1 contract(s) would be removed')
        ->assertSuccessful();

    expect(Contract::find($untouched->id))->not->toBeNull();
});
