<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Reward;

/**
 * Business logic for the `rewards` write surface that lives OUTSIDE the
 * Opportunity/Gestione Richiesta chip payload (spec 0059's
 * RewardAssignmentWriter remains the sole writer of every other field):
 * today, only the card's inline status edit (spec 0060 §4, D-1).
 */
class RewardService
{
    /**
     * Persists the card's inline status pick (BR-8: the FormRequest has
     * already confirmed $rewardStatusId is an existing, active status).
     */
    public function updateStatus(Reward $reward, int $rewardStatusId): Reward
    {
        $reward->update(['reward_status_id' => $rewardStatusId]);

        return $reward->fresh();
    }
}
