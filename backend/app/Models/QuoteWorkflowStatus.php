<?php

namespace App\Models;

use App\Enums\WorkflowStatusGroup;
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
 */
#[Fillable(['name', 'description', 'color', 'sort_order', 'group', 'requires_note'])]
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
}
