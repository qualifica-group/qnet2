<?php

namespace Database\Factories;

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteStatus;
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
            'quote_status_id' => QuoteStatus::factory(),
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
