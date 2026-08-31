<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * The `rewards` block every reward-ORIGIN resource embeds (spec 0059;
 * generalized to the Offerta origin by the 2026-08-31 directive): the chips
 * the record's edit form rehydrates its "abbinamento buono" control from,
 * ordered by `reward_type.name` (data contract).
 *
 * Shared verbatim by OpportunityResource and QuoteResource — the two origins
 * `HasRewards` is applied to — so the wire shape the SAME frontend control
 * consumes can never drift between them. Relies on the caller having
 * eager-loaded `rewards.rewardType` (both services declare it in their own
 * DETAIL_RELATIONS), so resolving the type never N+1s.
 */
trait SummarizesRewards
{
    /**
     * @return array<int, array{id: int, reward_type: array{id: int, name: string, color: string}, assigned_at: string|null, notes: string|null}>
     */
    private function summarizeRewards(iterable $rewards): array
    {
        return collect($rewards)
            ->sortBy(fn (Model $reward): string => $reward->rewardType->name)
            ->values()
            ->map(fn (Model $reward): array => [
                'id' => $reward->id,
                'reward_type' => [
                    'id' => $reward->rewardType->id,
                    'name' => $reward->rewardType->name,
                    'color' => $reward->rewardType->color,
                ],
                'assigned_at' => $reward->assigned_at?->toDateString(),
                'notes' => $reward->notes,
            ])
            ->all();
    }
}
