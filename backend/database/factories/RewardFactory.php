<?php

namespace Database\Factories;

use App\Models\Opportunity;
use App\Models\Referent;
use App\Models\Reward;
use App\Models\RewardStatus;
use App\Models\RewardType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @extends Factory<Reward>
 */
class RewardFactory extends Factory
{
    protected $model = Reward::class;

    /**
     * Default origin is an Opportunity — the only source today (spec 0059).
     * `source_type` is resolved through the morph map ALIAS
     * (`Relation::getMorphAlias()`, strictly enforced by
     * `AppServiceProvider::boot()`), never a raw FQCN (AC-002).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'referent_id' => Referent::factory(),
            'reward_type_id' => RewardType::factory(),
            'reward_status_id' => RewardStatus::factory(),
            'source_type' => Relation::getMorphAlias(Opportunity::class),
            'source_id' => Opportunity::factory(),
            'assigned_at' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
