<?php

namespace Database\Factories;

use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowCriterion;
use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteWorkflowCriterion>
 */
class QuoteWorkflowCriterionFactory extends Factory
{
    protected $model = QuoteWorkflowCriterion::class;

    /**
     * Default `field`/`value_id` pair targets `source_id` (the simplest
     * allow-listed direct-column criterion, App\Support\QuoteWorkflows\
     * CriterionFieldRegistry) — callers needing another field override both
     * keys via ->state().
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quote_workflow_id' => QuoteWorkflow::factory(),
            'field' => 'source_id',
            'value_id' => Source::factory(),
        ];
    }
}
