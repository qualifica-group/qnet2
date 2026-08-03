<?php

namespace Database\Seeders;

use App\Enums\RewardStatusGroup;
use App\Models\RewardStatus;
use Illuminate\Database\Seeder;

/**
 * Seed the reward status lookup (spec 0060) with a realistic progression on
 * top of the migration-seeded system row ("In attesa"/`pending`). `color`
 * uses one of the badge tokens from `BADGE_COLOR_TOKENS`
 * (frontend/src/features/custom-fields/badge-color-tokens.ts) — the grid
 * badge/ColorTokenPicker look the value up by TOKEN NAME, never an arbitrary
 * hex. Idempotent: `updateOrCreate` keyed by `name`, so re-running never
 * duplicates rows and refreshes color/description/sort_order/is_active in
 * place.
 *
 * `sort_order` starts at 20: 0 and 10 are reserved for the two system head
 * rows ("Aperto"/"In attesa", spec 0073 D-6), and the two closing system rows
 * are pushed past the last custom by StatusOrderManager.
 */
class DemoRewardStatusSeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, description: string, color: string, group: RewardStatusGroup, sort_order: int, is_active: bool}>
     */
    private const array STATUSES = [
        ['name' => 'Approvato', 'description' => 'Il buono e\' stato approvato ed e\' pronto per la consegna.', 'color' => 'green', 'group' => RewardStatusGroup::Pending, 'sort_order' => 20, 'is_active' => true],
        ['name' => 'Consegnato', 'description' => 'Il buono e\' stato consegnato al referente.', 'color' => 'blue', 'group' => RewardStatusGroup::ClosedWon, 'sort_order' => 30, 'is_active' => true],
        ['name' => 'Scaduto', 'description' => 'Il buono non e\' stato utilizzato entro la scadenza.', 'color' => 'red', 'group' => RewardStatusGroup::ClosedLost, 'sort_order' => 40, 'is_active' => false],
    ];

    public function run(): void
    {
        foreach (self::STATUSES as $status) {
            RewardStatus::query()->updateOrCreate(
                ['name' => $status['name']],
                [
                    'description' => $status['description'],
                    'color' => $status['color'],
                    'group' => $status['group'],
                    'sort_order' => $status['sort_order'],
                    'is_active' => $status['is_active'],
                ],
            );
        }
    }
}
