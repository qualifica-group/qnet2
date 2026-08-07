<?php

namespace App\Models\Concerns;

use App\Models\Reward;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Drop-in polymorphic reward/voucher origin for any model (spec 0059, D-12).
 *
 * Add `use HasRewards` to an owning model and the `rewards` morph is wired
 * automatically — no schema change (`rewards.source` is already a generic
 * morph column), no extra setup:
 *
 *     class Quote extends BaseModel
 *     {
 *         use HasRewards;
 *     }
 *
 * Extracted out of `Opportunity` (spec 0086, D-12) so `RewardAssignmentWriter`
 * can accept any model exposing this relation plus a `reporter_id` column —
 * today `Opportunity` and `Quote` alike — without duplicating the relation
 * declaration on each. All authoring logic stays in `RewardAssignmentWriter`
 * (models remain thin); the trait is just the relation any owner opts into.
 */
trait HasRewards
{
    /**
     * The vouchers/rewards/incentives whose origin is this model. Written
     * exclusively by `RewardAssignmentWriter::sync()`.
     *
     * @return MorphMany<Reward, $this>
     */
    public function rewards(): MorphMany
    {
        return $this->morphMany(Reward::class, 'source');
    }
}
