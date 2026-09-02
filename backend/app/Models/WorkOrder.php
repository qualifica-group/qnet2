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
 *
 * `attribute_values` (spec 0098, D-5) is the dynamic "Informazioni
 * aggiuntive" map, twin of `Quote::attribute_values` (spec 0084): JSON,
 * DELIBERATELY absent from #[Fillable] — written exclusively by
 * `App\Services\WorkOrders\WorkOrderAttributeValueWriter::apply()` after
 * per-`code` validation, never by mass assignment.
 */
#[Fillable([
    'quote_id',
    'title',
    'type',
    'start_date',
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
            'start_date' => 'date:Y-m-d',
            'callback_date' => 'date:Y-m-d',
            'is_force_closed' => 'boolean',
            'attribute_values' => 'array',
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
     * The internal Users accountable for this commessa — "Responsabili" in
     * the UI (spec 0096, D-1). MANY, and at least one: the floor is enforced
     * at the request layer (`supervisor_ids` required, min 1), not by the
     * pivot, which no engine can constrain that way.
     *
     * An unordered set, deliberately unlike participants(): there is no
     * "n-th responsabile" ranking to preserve.
     *
     * @return BelongsToMany<User, $this>
     */
    public function supervisors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'work_order_supervisor')->orderBy('users.name');
    }

    /**
     * The commessa's team — "Partecipanti" in the UI (spec 0096, D-3): the
     * SAME ordered, gap-aware slot apparatus as `Quote::managers()`/
     * `Opportunity::managers()`, over its own `work_order_participant` pivot,
     * so ValidatesManagerSlots, ManagerPositions::syncMap() and the shared
     * ManagerSlotsField all apply unchanged.
     *
     * @return BelongsToMany<User, $this>
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'work_order_participant')
            ->withPivot('position')
            ->orderByPivot('position');
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
