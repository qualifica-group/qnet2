<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\RewardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One assigned voucher/reward/incentive (spec 0059): the beneficiary is a
 * plain FK (`referent_id`, D-5 — the polymorphism lives on the ORIGIN, not
 * on the beneficiary), the type comes from the `reward_types` catalogue and
 * the origin is polymorphic (`source()`, today always an Opportunity).
 * No status/value column: "active"/"completed" are DERIVED at read time
 * from the origin's own state (D-2), never persisted here.
 *
 * `source_type`/`source_id` are deliberately NOT fillable: they are only
 * ever written through the `source()` relation (e.g.
 * `$reward->source()->associate($opportunity)`), never from raw request
 * input (RewardAssignmentWriter is the only writer, per the nested-sync
 * precedent).
 */
#[Fillable(['referent_id', 'reward_type_id', 'assigned_at', 'notes'])]
class Reward extends BaseModel
{
    /** @use HasFactory<RewardFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'date',
        ];
    }

    public function referent(): BelongsTo
    {
        return $this->belongsTo(Referent::class);
    }

    public function rewardType(): BelongsTo
    {
        return $this->belongsTo(RewardType::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
