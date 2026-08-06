<?php

namespace Database\Factories;

use App\Enums\WorkflowStatusGroup;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteWorkflowStatus>
 */
class QuoteWorkflowStatusFactory extends Factory
{
    protected $model = QuoteWorkflowStatus::class;

    /** Incrementing counter backing `sort_order`, reset per factory instance. */
    private static int $nextSortOrder = 1;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quote_workflow_id' => QuoteWorkflow::factory(),
            'name' => fake()->unique()->words(2, true),
            'description' => null,
            'color' => fake()->randomElement(['slate', 'green', 'red', 'blue']),
            'sort_order' => self::$nextSortOrder++,
            'system_key' => null,
            'group' => WorkflowStatusGroup::Open,
            'requires_note' => false,
        ];
    }

    /**
     * Marks the row as one of the four mandatory system rows ('open'/
     * 'validated'/'closed_won'/'closed_lost', spec 0047 AC-004),      * the same helper on the quote-status factory it replaced.
     */
    public function system(string $key): static
    {
        return $this->state(fn () => match ($key) {
            'validated' => ['system_key' => 'validated', 'name' => 'Validato', 'sort_order' => 997, 'group' => WorkflowStatusGroup::Validated],
            'closed_won' => ['system_key' => 'closed_won', 'name' => 'Chiusa positiva', 'sort_order' => 998, 'group' => WorkflowStatusGroup::ClosedWon],
            'closed_lost' => ['system_key' => 'closed_lost', 'name' => 'Chiusa negativa', 'sort_order' => 999, 'group' => WorkflowStatusGroup::ClosedLost],
            default => ['system_key' => 'open', 'name' => 'Aperta', 'sort_order' => 0, 'group' => WorkflowStatusGroup::Open],
        });
    }

    /**
     * Places the row in the GLOBAL default set (quote_workflow_id
     * null, AC-005/AC-010) rather than under a specific workflow.
     */
    public function global(): static
    {
        return $this->state(fn () => ['quote_workflow_id' => null]);
    }
}
