<?php

namespace Database\Factories;

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    /**
     * Default: the 3 mandatory columns (D-13/spec 0065 D-2); the snapshot
     * roles (`commercial_id`/`reporter_id`/`supervisor_id`, D-3) stay null,
     * matching OpportunityFactory's own default.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->company().' quote',
            'opportunity_id' => Opportunity::factory(),
            // Spec 0083 AC-020: a quote lands on the `open` row of the set
            // resolved for it, and with no workflow matching, that set IS the
            // global default one. Reusing the seeded row rather than minting a
            // fresh status per quote keeps the fixtures on the same status the
            // resolver would actually assign — a per-quote throwaway row would
            // belong to no set and quietly diverge from production behaviour.
            'quote_workflow_status_id' => fn (): int => QuoteWorkflowStatus::query()
                ->whereNull('quote_workflow_id')
                ->where('system_key', 'open')
                ->value('id')
                ?? QuoteWorkflowStatus::factory()->global()->system('open')->create()->id,
            'commercial_id' => null,
            'reporter_id' => null,
            'supervisor_id' => null,
            'internal_notes' => null,
        ];
    }

    /**
     * `code` (D-13: QUO-0001...) is service-generated in production and
     * deliberately NOT in the model's #[Fillable], so it must be assigned
     * directly (property assignment bypasses mass-assignment guarding) after
     * the instance is made, not through the fillable `definition()` array —
     * mirrors ProjectFactory::configure().
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Quote $quote): void {
            $quote->code ??= sprintf('QUO-%04d', fake()->unique()->numberBetween(1, 999999));
        });
    }
}
