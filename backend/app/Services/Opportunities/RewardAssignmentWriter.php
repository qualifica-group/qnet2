<?php

declare(strict_types=1);

namespace App\Services\Opportunities;

use App\Enums\StatusSystemKey;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Reward;
use App\Models\RewardStatus;

/**
 * The single write path for a reward origin's assignments (spec 0059,
 * D-3/`nested_sync_precedent`; generalized to Quote by spec 0086, D-12),
 * shared by every channel that can set them — mirrors
 * OpportunityProductInterestWriter's own full-replace discipline (one
 * writer, so the rule below can never diverge between channels).
 *
 * $owner is `Opportunity|Quote` — both use the `HasRewards` concern
 * (`rewards()` morphMany) and expose `reporter_id` — never any other model,
 * so a union type is enough here and no speculative interface is warranted
 * (engineering.md §1.3).
 *
 * THE RULE: the beneficiary is ALWAYS $owner's current Segnalatore
 * (`reporter_id`) — never chosen per row. For an Opportunity that is its own
 * `reporter_id`; for a Quote it is the OFFER's Segnalatore, per D-4.
 * `assigned_at` is set ONLY when a row is newly created; a row that survives
 * the sync keeps its original date (AC-020). Rows no longer in the submitted
 * set are deleted. The caller (FormRequest) is responsible for rejecting a
 * non-empty set against a reporter-less owner BEFORE this runs —
 * `referent_id` is NOT NULL at schema level, so a caller that skips that
 * guard fails loudly at the DB rather than orphaning a row silently.
 *
 * `reward_status_id` (spec 0060, BR-6/D-2): every newly created row starts on
 * the system `pending` row, resolved by `system_key` — never a hardcoded id.
 */
final class RewardAssignmentWriter
{
    /**
     * Replaces the whole set of reward-type assignments for $owner's origin
     * (via the `rewards()` morphMany).
     *
     * @param  array<int, int>  $rewardTypeIds
     */
    public function sync(Opportunity|Quote $owner, array $rewardTypeIds): void
    {
        $ids = array_values(array_unique(array_map(static fn ($id): int => (int) $id, $rewardTypeIds)));
        $existingTypeIds = $owner->rewards()->pluck('reward_type_id')->map(intval(...))->all();

        $this->deleteRemoved($owner, $existingTypeIds, $ids);
        $this->createAdded($owner, $existingTypeIds, $ids);

        $owner->unsetRelation('rewards');
    }

    /**
     * Reassigns EVERY existing reward row of $owner to its CURRENT
     * `reporter_id`: called whenever `reporter_id` actually changes,
     * independent of whether `rewards` itself was submitted in the same
     * request — an owner's rewards always follow its Segnalatore.
     *
     * Per-model save, not a bulk query-builder `update()`: LogsModelActivity
     * hooks Eloquent's `saving`/`saved` events, which a bulk UPDATE bypasses
     * entirely (AC-024).
     */
    public function retarget(Opportunity|Quote $owner): void
    {
        $owner->rewards()->get()->each(
            fn (Reward $reward): bool => $reward->update(['referent_id' => $owner->reporter_id]),
        );

        $owner->unsetRelation('rewards');
    }

    /**
     * Per-model delete, not a bulk query-builder `delete()`: same
     * activity-log rationale as retarget() above (AC-024).
     *
     * @param  array<int, int>  $existingTypeIds
     * @param  array<int, int>  $ids
     */
    private function deleteRemoved(Opportunity|Quote $owner, array $existingTypeIds, array $ids): void
    {
        $removed = array_diff($existingTypeIds, $ids);

        if ($removed === []) {
            return;
        }

        $owner->rewards()->whereIn('reward_type_id', $removed)->get()->each(
            fn (Reward $reward): ?bool => $reward->delete(),
        );
    }

    /**
     * @param  array<int, int>  $existingTypeIds
     * @param  array<int, int>  $ids
     */
    private function createAdded(Opportunity|Quote $owner, array $existingTypeIds, array $ids): void
    {
        $added = array_diff($ids, $existingTypeIds);

        if ($added === []) {
            return;
        }

        $assignedAt = now()->toDateString();
        $pendingStatusId = $this->resolvePendingStatusId();

        foreach ($added as $rewardTypeId) {
            $owner->rewards()->create([
                'referent_id' => $owner->reporter_id,
                'reward_type_id' => $rewardTypeId,
                'reward_status_id' => $pendingStatusId,
                'assigned_at' => $assignedAt,
            ]);
        }
    }

    /**
     * Resolved once per batch (not per row): the system `pending` row's id
     * (BR-6), never hardcoded.
     */
    private function resolvePendingStatusId(): int
    {
        return (int) RewardStatus::query()->where('system_key', StatusSystemKey::Pending->value)->value('id');
    }
}
