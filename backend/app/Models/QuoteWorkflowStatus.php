<?php

namespace App\Models;

use App\Enums\WorkflowStatusGroup;
use App\Enums\WorkflowStatusSystemKey;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\QuoteWorkflowStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Workflow status lookup entity (spec 0047; moved onto the Offerta by spec
 * 0083, D-6/D-8): the "stato dell'offerta" pick list row for a Quote.
 * Belongs to a workflow (`quote_workflow_id`) OR to the GLOBAL default set
 * when null (AC-005/AC-010) — the latter also the fallback an Opportunity
 * with zero Quotes displays (D-8). `system_key`/`quote_workflow_id` are
 * DELIBERATELY absent from #[Fillable] — never mass-assignable, written only
 * by the migration seed and the service that creates/syncs a workflow's
 * status set. `group` (App\Enums\WorkflowStatusGroup) classifies the row as
 * open/pending/validated/closed_won/closed_lost — the closed phase carries
 * its outcome. `description` is the free-text explanation surfaced in the
 * configurator, the working-status select and the table badge tooltip;
 * `requires_note` marks a status whose destination demands an explanatory
 * note, enforced by App\Services\Quotes\QuoteWorkflowStatusWriter (spec
 * 0083, T-04).
 *
 * `quote_system_status_id` (spec 0404 part A) links the "stato di sistema"
 * Offerte shows; `group` is its denormalized copy (A-D5). The saving hook
 * keeps the two in step for every write path (configurator, import, seeders,
 * factories), see linkSystemStatus().
 */
#[Fillable(['name', 'description', 'color', 'sort_order', 'group', 'requires_note', 'quote_system_status_id'])]
class QuoteWorkflowStatus extends BaseModel
{
    /** @use HasFactory<QuoteWorkflowStatusFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'int',
            'group' => WorkflowStatusGroup::class,
            'requires_note' => 'bool',
        ];
    }

    protected static function booted(): void
    {
        static::saving(static function (QuoteWorkflowStatus $status): void {
            $status->linkSystemStatus();
        });
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(QuoteWorkflow::class, 'quote_workflow_id');
    }

    /**
     * Whether this is a pinned system row ('open'/'closed_won'/'closed_lost',
     * AC-004) rather than a custom, user-created status.
     */
    public function isSystem(): bool
    {
        return $this->system_key !== null;
    }

    /**
     * @return BelongsTo<QuoteSystemStatus, $this>
     */
    public function systemStatus(): BelongsTo
    {
        return $this->belongsTo(QuoteSystemStatus::class, 'quote_system_status_id');
    }

    /**
     * Spec 0404 A-D4/A-D5: a system row links the system status with its own
     * key; an explicitly linked row takes its `group` from the link; a row
     * whose `group` alone was written (no link yet, or the group changed)
     * links the first system status with that behavior.
     */
    private function linkSystemStatus(): void
    {
        if ($this->system_key !== null && WorkflowStatusSystemKey::tryFrom($this->system_key) !== null) {
            $linked = QuoteSystemStatus::query()->where('system_key', $this->system_key)->first();
        } elseif ($this->quote_system_status_id !== null && ($this->isDirty('quote_system_status_id') || ! $this->isDirty('group'))) {
            $linked = QuoteSystemStatus::query()->find($this->quote_system_status_id);
        } else {
            $linked = $this->firstSystemStatusFor($this->group);
        }

        if ($linked === null) {
            return;
        }

        $this->quote_system_status_id = $linked->id;
        $this->group = $linked->behavior;
    }

    private function firstSystemStatusFor(WorkflowStatusGroup|string|null $group): ?QuoteSystemStatus
    {
        $behavior = $group instanceof WorkflowStatusGroup ? $group->value : $group;

        if ($behavior === null) {
            return null;
        }

        $current = $this->quote_system_status_id !== null ? QuoteSystemStatus::query()->find($this->quote_system_status_id) : null;

        if ($current !== null && $current->behavior->value === $behavior) {
            return $current;
        }

        return QuoteSystemStatus::query()->where('behavior', $behavior)->orderBy('sort_order')->orderBy('id')->first();
    }
}
