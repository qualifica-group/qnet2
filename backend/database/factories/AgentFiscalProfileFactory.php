<?php

namespace Database\Factories;

use App\Models\AgentFiscalProfile;
use App\Models\Referent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentFiscalProfile>
 */
class AgentFiscalProfileFactory extends Factory
{
    protected $model = AgentFiscalProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'referent_id' => Referent::factory(),
            'withholding_enabled' => true,
            'withholding_rate' => '23.00',
            'withholding_base_percent' => '50.00',
            'enasarco_enabled' => false,
            'enasarco_mandate_type' => null,
            'valid_from' => '2026-01-01',
            'valid_until' => null,
            'notes' => null,
        ];
    }

    public function withEnasarco(string $type = 'pluri'): static
    {
        return $this->state(fn (): array => [
            'enasarco_enabled' => true,
            'enasarco_mandate_type' => $type,
        ]);
    }

    public function withoutWithholding(): static
    {
        return $this->state(fn (): array => [
            'withholding_enabled' => false,
            'withholding_rate' => null,
            'withholding_base_percent' => null,
        ]);
    }
}
