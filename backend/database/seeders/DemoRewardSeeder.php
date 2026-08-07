<?php

namespace Database\Seeders;

use App\Enums\StatusSystemKey;
use App\Models\Quote;
use App\Models\Reward;
use App\Models\RewardStatus;
use App\Models\RewardType;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Development seed for the reward assignment module (spec 0059, migrated onto
 * the Offerta by spec 0086 D-4): picks a subset of existing quotes that
 * already have a Segnalatore (`reporter_id`, D-3 — the beneficiary is always
 * the offer's current reporter, never choosable) and assigns them 1-2
 * EXISTING reward types, exercising the aggregate list (`rewarded-referents`)
 * and its master/detail without creating a single referent/quote/reward-type
 * of its own.
 *
 * No-op if there is no quote with a reporter, or no reward type — both are
 * seeded by earlier steps of DemoDataSeeder, but this stays defensive on a
 * partial run. Idempotent: `firstOrCreate` keyed on the same 4 columns as
 * `rewards_unique_assignment`, so a re-run never duplicates a row.
 *
 * Depends on DemoRewardTypeSeeder (catalogue) and DemoQuoteSeeder (reporters,
 * snapshotted from their opportunity at creation), both seeded earlier in
 * DemoDataSeeder — must run after both.
 */
class DemoRewardSeeder extends Seeder
{
    private const int MAX_QUOTES = 20;

    private const int MAX_REWARD_TYPES_PER_QUOTE = 2;

    public function run(): void
    {
        $quotes = Quote::query()
            ->whereNotNull('reporter_id')
            ->orderBy('id')
            ->limit(self::MAX_QUOTES)
            ->get();

        $rewardTypes = RewardType::query()->orderBy('id')->get();
        $pendingStatusId = $this->resolvePendingStatusId();

        if ($quotes->isEmpty() || $rewardTypes->isEmpty() || $pendingStatusId === null) {
            // Nothing sensible to seed without a reporter to reward, a
            // catalogue to reward it from, or the system status every new
            // reward starts on (spec 0060, BR-6).
            return;
        }

        $faker = FakerFactory::create('it_IT');
        $faker->seed(20260723);

        foreach ($quotes as $quote) {
            $this->assignRewards($quote, $rewardTypes, $pendingStatusId, $faker);
        }
    }

    /**
     * The system `pending` row's id (spec 0060, BR-6), resolved by
     * `system_key` exactly like RewardAssignmentWriter — never a hardcoded id.
     */
    private function resolvePendingStatusId(): ?int
    {
        $id = RewardStatus::query()->where('system_key', StatusSystemKey::Pending->value)->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @param  Collection<int, RewardType>  $rewardTypes
     */
    private function assignRewards(Quote $quote, Collection $rewardTypes, int $pendingStatusId, Generator $faker): void
    {
        $count = $faker->numberBetween(1, min(self::MAX_REWARD_TYPES_PER_QUOTE, $rewardTypes->count()));
        $pickedRewardTypeIds = $faker->randomElements($rewardTypes->pluck('id')->all(), $count);

        foreach ($pickedRewardTypeIds as $rewardTypeId) {
            Reward::query()->firstOrCreate(
                [
                    'referent_id' => $quote->reporter_id,
                    'reward_type_id' => $rewardTypeId,
                    'source_type' => Relation::getMorphAlias(Quote::class),
                    'source_id' => $quote->id,
                ],
                [
                    'reward_status_id' => $pendingStatusId,
                    'assigned_at' => $faker->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
                    'notes' => $faker->optional()->sentence(),
                ],
            );
        }
    }
}
