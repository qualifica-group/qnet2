<?php

namespace Database\Seeders;

use App\Models\RewardType;
use Illuminate\Database\Seeder;

/**
 * Seed the reward type lookup (spec 0058) with a realistic catalogue of
 * voucher/reward/incentive types. `color` uses one of the badge tokens from
 * `BADGE_COLOR_TOKENS` (frontend/src/features/custom-fields/badge-color-tokens.ts)
 * — the grid badge/ColorTokenPicker look the value up by TOKEN NAME, never an
 * arbitrary hex. Idempotent: `updateOrCreate` keyed by `name`, so re-running
 * never duplicates rows and refreshes `color` in place (AC-026).
 */
class DemoRewardTypeSeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, color: string}>
     */
    private const array REWARD_TYPES = [
        ['name' => 'Buono Amazon', 'color' => 'orange'],
        ['name' => 'Buono carburante', 'color' => 'blue'],
        ['name' => 'Buono pasto', 'color' => 'green'],
        ['name' => 'Gadget aziendale', 'color' => 'violet'],
        ['name' => 'Premio in denaro', 'color' => 'emerald'],
    ];

    public function run(): void
    {
        foreach (self::REWARD_TYPES as $rewardType) {
            RewardType::query()->updateOrCreate(
                ['name' => $rewardType['name']],
                ['color' => $rewardType['color']],
            );
        }
    }
}
