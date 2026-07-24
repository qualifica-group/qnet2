<?php

namespace App\Models;

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
 * Cloned from OpportunityStatus MINUS `group` (no open/pending/closed
 * semantics), PLUS `description`/`is_active`. `name` is unique (BR-1);
 * `system_key` (nullable, the ONE mandatory "In attesa"/`pending` row, D-2)
 * is DELIBERATELY absent from #[Fillable] — never mass-assignable, written
 * only by the create migration and
 * App\Services\Statuses\SystemStatusGuard/StatusOrderManager. `sort_order`
 * stays fillable: server-managed (StatusOrderManager places/reorders it),
 * not user-fillable at the FormRequest layer.
 */
#[Fillable(['name', 'description', 'color', 'sort_order', 'is_active'])]
class RewardStatus extends BaseModel
{
    /** @use HasFactory<RewardStatusFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * The system row pinned to sort_order=0 (StatusOrderManager::reorder()).
     */
    public const StatusSystemKey SYSTEM_HEAD_KEY = StatusSystemKey::Pending;

    /**
     * No system row pins to the tail (spec 0060 D-3): "In attesa" is a
     * head-only system row, unlike OpportunityStatus'/PipelineStatus' own
     * closing row(s).
     *
     * @var array<int, StatusSystemKey>
     */
    public const array SYSTEM_TAIL_KEYS = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'int',
            'is_active' => 'bool',
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
     * Whether this is the one mandatory system row ("In attesa", spec 0060
     * D-2) rather than a custom, user-created status.
     */
    public function isSystem(): bool
    {
        return $this->system_key !== null;
    }
}
