<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WorkflowStatusGroup;
use App\Enums\WorkflowStatusSystemKey;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\QuoteSystemStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "Stato di sistema" of the Offerta (spec 0404 part A): the configurable
 * status Offerte shows, linked by every working status
 * (`quote_workflow_statuses.quote_system_status_id`). `behavior` is what the
 * code knows (a WorkflowStatusGroup value, A-D2) and is copied onto the
 * `group` of the linked working statuses, so every rule keyed on `group`
 * applies unchanged to a row added by hand (A-D0).
 *
 * `system_key` marks the three mandatory rows (open/closed_won/closed_lost,
 * A-D3): never deletable, behavior fixed. It is absent from #[Fillable] and
 * written only by the create migration.
 */
#[Fillable(['name', 'color', 'sort_order', 'behavior'])]
class QuoteSystemStatus extends BaseModel
{
    /** @use HasFactory<QuoteSystemStatusFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * Pinned first by StatusOrderManager.
     *
     * @var array<int, WorkflowStatusSystemKey>
     */
    public const array SYSTEM_HEAD_KEYS = [WorkflowStatusSystemKey::Open];

    /**
     * Pinned last by StatusOrderManager, in this order.
     *
     * @var array<int, WorkflowStatusSystemKey>
     */
    public const array SYSTEM_TAIL_KEYS = [WorkflowStatusSystemKey::ClosedWon, WorkflowStatusSystemKey::ClosedLost];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'int',
            'behavior' => WorkflowStatusGroup::class,
        ];
    }

    /**
     * @return HasMany<QuoteWorkflowStatus, $this>
     */
    public function workflowStatuses(): HasMany
    {
        return $this->hasMany(QuoteWorkflowStatus::class);
    }

    public function isSystem(): bool
    {
        return $this->system_key !== null;
    }
}
