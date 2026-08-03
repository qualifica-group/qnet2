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
 *
 * `reward_status_id` (spec 0060, D-5): NOT NULL, defaulted to the system
 * `pending` row on create by RewardAssignmentWriter::createAdded() (BR-6),
 * mutated ONLY through `PATCH /api/rewards/{reward}`
 * (RewardController::updateStatus, D-1) — never part of the Opportunity/
 * Gestione Richiesta chip payload, and never derived from the originating
 * request's working status (user directive 2026-08-03: the spec 0073
 * automation that did so is gone, a buono moves only when a human moves it).
 */
#[Fillable(['referent_id', 'reward_type_id', 'reward_status_id', 'assigned_at', 'notes'])]
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

    public function rewardStatus(): BelongsTo
    {
        return $this->belongsTo(RewardStatus::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
