<?php

namespace Database\Factories;

use App\Models\PersonalData;
use App\Models\Registry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Registry>
 */
class RegistryFactory extends Factory
{
    protected $model = Registry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'source_id' => null,
            'vat_group' => fake()->optional()->bothify('VG-####'),
            'is_supplier' => false,
            'is_qualified_supplier' => false,
            'agreement_status' => null,
            'agreement_notes' => null,
            'size_class' => null,
            'supervisor_id' => null,
            'commercial_id' => null,
            'commercial_partner_id' => null,
            'reporter_id' => null,
            'employee_count' => fake()->optional()->numberBetween(1, 500),
        ];
    }

    /**
     * A registry also managed as a supplier.
     */
    public function supplier(): static
    {
        return $this->state(fn (): array => [
            'is_supplier' => true,
        ]);
    }

    /**
     * A qualified supplier (implies is_supplier — spec 0020 business rule).
     */
    public function qualifiedSupplier(): static
    {
        return $this->state(fn (): array => [
            'is_supplier' => true,
            'is_qualified_supplier' => true,
        ]);
    }

    /**
     * Attach a personal-data card (morph `personable`, mirrors
     * ReferentFactory::withPersonalData), then re-derive `name` from the
     * card's display name so it stays consistent with the denormalized
     * `registries.name` column.
     */
    public function withPersonalData(?callable $factory = null): static
    {
        return $this->afterCreating(function (Registry $registry) use ($factory): void {
            $card = PersonalData::factory();
            $card = $factory !== null ? $factory($card) : $card;

            /** @var PersonalData $card */
            $card = $card->for($registry, 'personable')->create();

            $registry->forceFill(['name' => $card->full_name])->save();
        });
    }
}
