<?php

namespace App\Models;

use App\Enums\RewardStatusGroup;
use App\Enums\StatusSystemKey;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\RewardStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Reward status lookup entity (spec 0060): a persisted status configurator
 * describing the STATE of an assigned reward (`rewards.reward_status_id`).
 * Cloned from OpportunityStatus PLUS `description`/`is_active`. `name` is
 * unique (BR-1); `system_key` (nullable, the FOUR mandatory rows below) is
 * DELIBERATELY absent from #[Fillable] — never mass-assignable, written only
 * by the migrations and App\Services\Statuses\SystemStatusGuard/
 * StatusOrderManager. `sort_order` stays fillable: server-managed
 * (StatusOrderManager places/reorders it), not user-fillable at the
 * FormRequest layer.
 *
 * Spec 0073 SUPERSEDES spec 0060 D-3 on two points: `group`
 * (App\Enums\RewardStatusGroup) is added and the single "In attesa" system
 * row becomes THREE, one pinned to the head and two to the tail (user
 * directive 2026-08-03: "In attesa", "Approvato", "Negato" — the "Aperto"
 * head row of the original spec was dropped along with the `open` phase).
 */
#[Fillable(['name', 'description', 'color', 'group', 'sort_order', 'is_active'])]
class RewardStatus extends BaseModel
{
    /** @use HasFactory<RewardStatusFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * The system row pinned to the head of the sort_order sequence
     * (StatusOrderManager::reorder()): "In attesa" at 0, the row every new
     * reward is born on (RewardAssignmentWriter, spec 0060 BR-6).
     *
     * @var array<int, StatusSystemKey>
     */
    public const array SYSTEM_HEAD_KEYS = [StatusSystemKey::Pending];

    /**
     * The system rows pinned to the tail, in the order they must appear:
     * "Approvato" then "Negato" (spec 0073 D-6, renamed by the user directive
     * of 2026-08-03).
     *
     * @var array<int, StatusSystemKey>
     */
    public const array SYSTEM_TAIL_KEYS = [StatusSystemKey::Won, StatusSystemKey::Lost];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'int',
            'is_active' => 'bool',
            'group' => RewardStatusGroup::class,
        ];
    }

    /**
     * The rewards classified under this status. `reward_status_id` is
     * restrictOnDelete (migration, BR-4): deleting a status referenced by a
     * reward is rejected at the schema layer too.
     */
    public function rewards(): HasMany
    {
        return $this->hasMany(Reward::class);
    }

    /**
     * Whether this is one of the three mandatory system rows ("In attesa"/
     * "Approvato"/"Negato") rather than a custom, user-created status.
     */
    public function isSystem(): bool
    {
        return $this->system_key !== null;
    }
}
