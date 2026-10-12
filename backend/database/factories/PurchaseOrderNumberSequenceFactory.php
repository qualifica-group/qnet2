<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\PurchaseOrderNumberSequence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderNumberSequence>
 */
class PurchaseOrderNumberSequenceFactory extends Factory
{
    protected $model = PurchaseOrderNumberSequence::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'year' => (int) now()->format('Y'),
            'last_number' => 0,
        ];
    }
}
