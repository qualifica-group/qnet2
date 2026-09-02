<?php

namespace App\Models;

use App\Enums\WorkOrderType;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\WorkOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * WorkOrder entity (spec 0093, D-1): "Commessa", numbered `COM-0001...`,
 * belonging to exactly one Quote and zero-or-more of that same Quote's
 * REVENUE lines (`quoteLines()`, via the dedicated `quote_line_work_order`
 * pivot, D-6). `code` is DELIBERATELY absent from #[Fillable] (D-1): the
 * service assigns it after mass-assignment, inside the create transaction,
 * exactly like `Quote::code`.
 *
 * `is_force_closed`/`force_close_reason` (D-4) are the only client-writable
 * "state": the working status itself (`open`/`closed`) is ALWAYS computed at
 * read time by `App\Services\WorkOrders\WorkOrderStatusResolver` (D-3), never
 * a column on this table.
 */
#[Fillable([
    'quote_id',
    'title',
    'type',
    'callback_date',
    'description',
    'internal_notes',
    'is_force_closed',
    'force_close_reason',
])]
class WorkOrder extends BaseModel
{
    /** @use HasFactory<WorkOrderFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => WorkOrderType::class,
            'callback_date' => 'date:Y-m-d',
            'is_force_closed' => 'boolean',
        ];
    }

    /**
     * The offer this commessa is scoped to (D-5): restrictOnDelete,
     * IMMUTABLE after creation — enforced by UpdateWorkOrderRequest's own
     * `prohibited` rule, not here.
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * The offer REVENUE lines this commessa covers (D-6/D-7): a dedicated
     * pivot with its own `id` (quote_line_work_order), never a bare default
     * pivot — the extension point for a future per-row column.
     *
     * @return BelongsToMany<QuoteLine, $this>
     */
    public function quoteLines(): BelongsToMany
    {
        return $this->belongsToMany(QuoteLine::class, 'quote_line_work_order')->orderBy('sort_order');
    }
}
