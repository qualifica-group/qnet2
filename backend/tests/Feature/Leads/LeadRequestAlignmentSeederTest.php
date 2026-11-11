<?php

use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Source;
use App\Models\User;
use Database\Seeders\LeadRequestAlignmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Task 427463: the one-shot aligning the requests born from a lead with the
 * lead's Fonte and Operatore, filling only empty fields.
 */
uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $lead
 * @param  array<string, mixed>  $quote
 */
function leadAlignmentQuote(array $lead, array $quote): Quote
{
    $opportunity = Opportunity::factory()->create(['lead_id' => Lead::factory()->create($lead)->id]);

    return Quote::factory()->create(['opportunity_id' => $opportunity->id, ...$quote]);
}

it('fills the empty Fonte and Operatore from the lead, idempotently', function () {
    $source = Source::factory()->create();
    $operator = User::factory()->create();
    $quote = leadAlignmentQuote(['source_id' => $source->id, 'operator_id' => $operator->id], ['source_id' => null, 'operator_id' => null]);

    $this->seed(LeadRequestAlignmentSeeder::class);
    $this->seed(LeadRequestAlignmentSeeder::class);

    $quote->refresh();
    expect($quote->source_id)->toBe($source->id)
        ->and($quote->operator_id)->toBe($operator->id)
        ->and($quote->managers()->pluck('users.id')->all())->toBe([$operator->id]);
});

it('never overwrites a Fonte or Operatore set on the request, nor touches lead-less requests', function () {
    $handSource = Source::factory()->create();
    $handOperator = User::factory()->create();
    $set = leadAlignmentQuote(
        ['source_id' => Source::factory()->create()->id, 'operator_id' => User::factory()->create()->id],
        ['source_id' => $handSource->id, 'operator_id' => $handOperator->id],
    );
    $withoutLead = Quote::factory()->create(['source_id' => null, 'operator_id' => null]);

    $this->seed(LeadRequestAlignmentSeeder::class);

    expect($set->fresh()->source_id)->toBe($handSource->id)
        ->and($set->fresh()->operator_id)->toBe($handOperator->id)
        ->and($withoutLead->fresh()->source_id)->toBeNull()
        ->and($withoutLead->fresh()->operator_id)->toBeNull();
});
