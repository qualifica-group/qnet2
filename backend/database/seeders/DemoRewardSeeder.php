<?php

namespace Database\Seeders;

use App\Models\Opportunity;
use App\Models\Reward;
use App\Models\RewardType;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Development seed for the reward assignment module (spec 0059): picks a
 * subset of existing opportunities that already have a Segnalatore
 * (`reporter_id`, D-3 — the beneficiary is always the current reporter,
 * never choosable) and assigns them 1-2 EXISTING reward types, exercising
 * the aggregate list (`rewarded-referents`) and its master/detail without
 * creating a single referent/opportunity/reward-type of its own.
 *
 * No-op if there is no opportunity with a reporter, or no reward type —
 * both are seeded by earlier steps of DemoDataSeeder, but this stays
 * defensive on a partial run. Idempotent: `firstOrCreate` keyed on the same
 * 4 columns as `rewards_unique_assignment`, so a re-run never duplicates a
 * row.
 *
 * Depends on DemoRewardTypeSeeder (catalogue) and DemoOpportunitySeeder
 * (reporters), both seeded earlier in DemoDataSeeder — must run after both.
 */
class DemoRewardSeeder extends Seeder
{
    private const int MAX_OPPORTUNITIES = 20;

    private const int MAX_REWARD_TYPES_PER_OPPORTUNITY = 2;

    public function run(): void
    {
        $opportunities = Opportunity::query()
            ->whereNotNull('reporter_id')
            ->orderBy('id')
            ->limit(self::MAX_OPPORTUNITIES)
            ->get();

        $rewardTypes = RewardType::query()->orderBy('id')->get();

        if ($opportunities->isEmpty() || $rewardTypes->isEmpty()) {
            // Nothing sensible to seed without a reporter to reward or a
            // catalogue to reward it from.
            return;
        }

        $faker = FakerFactory::create('it_IT');
        $faker->seed(20260723);

        foreach ($opportunities as $opportunity) {
            $this->assignRewards($opportunity, $rewardTypes, $faker);
        }
    }

    /**
     * @param  Collection<int, RewardType>  $rewardTypes
     */
    private function assignRewards(Opportunity $opportunity, Collection $rewardTypes, Generator $faker): void
    {
        $count = $faker->numberBetween(1, min(self::MAX_REWARD_TYPES_PER_OPPORTUNITY, $rewardTypes->count()));
        $pickedRewardTypeIds = $faker->randomElements($rewardTypes->pluck('id')->all(), $count);

        foreach ($pickedRewardTypeIds as $rewardTypeId) {
            Reward::query()->firstOrCreate(
                [
                    'referent_id' => $opportunity->reporter_id,
                    'reward_type_id' => $rewardTypeId,
                    'source_type' => Relation::getMorphAlias(Opportunity::class),
                    'source_id' => $opportunity->id,
                ],
                [
                    'assigned_at' => $faker->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
                    'notes' => $faker->optional()->sentence(),
                ],
            );
        }
    }
}
