<?php

declare(strict_types=1);

namespace App\Services\Opportunities;

use App\Enums\StatusSystemKey;
use App\Models\Opportunity;
use App\Models\Reward;
use App\Models\RewardStatus;

/**
 * The single write path for an opportunity's reward assignments (spec 0059,
 * D-3/`nested_sync_precedent`), shared by every channel that can set them —
 * mirrors OpportunityProductInterestWriter's own full-replace discipline (one
 * writer, so the rule below can never diverge between channels).
 *
 * THE RULE: the beneficiary is ALWAYS the opportunity's current Segnalatore
 * (`reporter_id`, D-3) — never chosen per row. `assigned_at` is set ONLY when
 * a row is newly created; a row that survives the sync keeps its original
 * date (AC-020). Rows no longer in the submitted set are deleted. The caller
 * (FormRequest, D-3) is responsible for rejecting a non-empty set against a
 * reporter-less opportunity BEFORE this runs — `referent_id` is NOT NULL at
 * schema level, so a caller that skips that guard fails loudly at the DB
 * rather than orphaning a row silently.
 *
 * `reward_status_id` (spec 0060, BR-6/D-2): every newly created row starts on
 * the system `pending` row, resolved by `system_key` — never a hardcoded id.
 */
final class RewardAssignmentWriter
{
    /**
     * Replaces the whole set of reward-type assignments for $opportunity's
     * origin (source_type='opportunity', via the `rewards()` morphMany).
     *
     * @param  array<int, int>  $rewardTypeIds
     */
    public function sync(Opportunity $opportunity, array $rewardTypeIds): void
    {
        $ids = array_values(array_unique(array_map(static fn ($id): int => (int) $id, $rewardTypeIds)));
        $existingTypeIds = $opportunity->rewards()->pluck('reward_type_id')->map(intval(...))->all();

        $this->deleteRemoved($opportunity, $existingTypeIds, $ids);
        $this->createAdded($opportunity, $existingTypeIds, $ids);

        $opportunity->unsetRelation('rewards');
    }

    /**
     * Reassigns EVERY existing reward row of $opportunity to its CURRENT
     * `reporter_id` (D-3/AC-022): called whenever `reporter_id` actually
     * changes, independent of whether `rewards` itself was submitted in the
     * same request — an opportunity's rewards always follow its Segnalatore.
     *
     * Per-model save, not a bulk query-builder `update()`: LogsModelActivity
     * hooks Eloquent's `saving`/`saved` events, which a bulk UPDATE bypasses
     * entirely (AC-024).
     */
    public function retarget(Opportunity $opportunity): void
    {
        $opportunity->rewards()->get()->each(
            fn (Reward $reward): bool => $reward->update(['referent_id' => $opportunity->reporter_id]),
        );

        $opportunity->unsetRelation('rewards');
    }

    /**
     * Per-model delete, not a bulk query-builder `delete()`: same
     * activity-log rationale as retarget() above (AC-024).
     *
     * @param  array<int, int>  $existingTypeIds
     * @param  array<int, int>  $ids
     */
    private function deleteRemoved(Opportunity $opportunity, array $existingTypeIds, array $ids): void
    {
        $removed = array_diff($existingTypeIds, $ids);

        if ($removed === []) {
            return;
        }

        $opportunity->rewards()->whereIn('reward_type_id', $removed)->get()->each(
            fn (Reward $reward): ?bool => $reward->delete(),
        );
    }

    /**
     * @param  array<int, int>  $existingTypeIds
     * @param  array<int, int>  $ids
     */
    private function createAdded(Opportunity $opportunity, array $existingTypeIds, array $ids): void
    {
        $added = array_diff($ids, $existingTypeIds);

        if ($added === []) {
            return;
        }

        $assignedAt = now()->toDateString();
        $pendingStatusId = $this->resolvePendingStatusId();

        foreach ($added as $rewardTypeId) {
            $opportunity->rewards()->create([
                'referent_id' => $opportunity->reporter_id,
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
