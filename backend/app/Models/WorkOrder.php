<?php

namespace App\Models;

use App\Enums\WorkOrderType;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasNotes;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\WorkOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * WorkOrder entity (spec 0093, D-1): "Commessa", numbered `COM-0001...`,
 * belonging to one Quote (or, spec 0267, to several via its lines) and zero-or-more of that same Quote's
 * REVENUE lines (`quoteLines()`, via the dedicated `quote_line_work_order`
 * pivot, D-6). `code` is DELIBERATELY absent from #[Fillable] (D-1): the
 * service assigns it after mass-assignment, inside the create transaction,
 * exactly like `Quote::code`.
 *
 * `is_force_closed`/`force_close_reason` (D-4) are the only client-writable
 * "state": the working status itself (open, in progress, completed or closed) is ALWAYS computed at
 * read time by `App\Services\WorkOrders\WorkOrderStatusResolver` (D-3), never
 * a column on this table.
 *
 * `attribute_values` (spec 0098, D-5) is the dynamic "Informazioni
 * aggiuntive" map, twin of `Quote::attribute_values` (spec 0084): JSON,
 * DELIBERATELY absent from #[Fillable] — written exclusively by
 * `App\Services\WorkOrders\WorkOrderAttributeValueWriter::apply()` after
 * per-`code` validation, never by mass assignment.
 *
 * `title_is_manual` (spec 0215) is DELIBERATELY absent from #[Fillable]:
 * written only by `WorkOrderTitleWriter` via forceFill, like `Quote`.
 *
 * Spec 0134 adds the two collaborative concerns: `HasNotes` (the thread,
 * registered as the `work-orders` host in config/notes.php via
 * WorkOrderNotable) and `HasAttachments` (the documents, alias `work_order`
 * in config/attachments.php).
 *
 * Spec 0267 (D-4): `quote_id` is NULLABLE; a NULL one marks a MULTI-OFFER
 * commessa (isMultiQuote()), whose source offers are derived from the
 * `quote_line_work_order` pivot (sourceQuoteIds()), with no extra pivot.
 *
 * `task_template_id` (spec 0124, D-5/D-7) IS fillable — unlike `code`, it is
 * genuine client input (`CreateWorkOrderData`), the same category as
 * `quote_id`: mass-assignable on the model, with its post-create
 * immutability enforced one layer up by `UpdateWorkOrderRequest`'s own
 * `prohibited` rule, not by omitting it here.
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
    'task_template_id',
])]
class WorkOrder extends BaseModel
{
    /** @use HasFactory<WorkOrderFactory> */
    use HasAttachments, HasFactory, HasNotes, LogsModelActivity;

    /** Relations whose rows block deleting the commessa (spec 0243, D-1). */
    private const array ACCOUNTING_RELATIONS = ['proformaRequests', 'invoices', 'purchaseOrders', 'purchaseOrderLines', 'purchaseInvoices'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => WorkOrderType::class,
            'start_date' => 'date:Y-m-d',
            'callback_date' => 'date:Y-m-d',
            'title_is_manual' => 'boolean',
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
     * Multi-offer commessa (spec 0267, D-4): no offer of its own.
     */
    public function isMultiQuote(): bool
    {
        return $this->quote_id === null;
    }

    /**
     * Distinct ids of the offers this commessa draws from (spec 0267, D-4):
     * its own `quote_id` (mono-offer) merged with the offers of the pivot lines.
     *
     * @return list<int>
     */
    public function sourceQuoteIds(): array
    {
        $ids = DB::table('quote_line_work_order')
            ->join('quote_lines', 'quote_lines.id', '=', 'quote_line_work_order.quote_line_id')
            ->where('quote_line_work_order.work_order_id', $this->getKey())
            ->distinct()
            ->pluck('quote_lines.quote_id')
            ->map(static fn (mixed $id): int => (int) $id);

        if ($this->quote_id !== null) {
            $ids->push((int) $this->quote_id);
        }

        return $ids->unique()->sort()->values()->all();
    }

    /**
     * Work orders tied to the offer directly or through one of their lines.
     *
     * @param  Builder<WorkOrder>  $query
     */
    public function scopeWithQuoteLineOfQuote(Builder $query, int $quoteId): void
    {
        $query->where(function (Builder $query) use ($quoteId): void {
            $query->where('work_orders.quote_id', $quoteId)
                ->orWhereExists(self::pivotLinesQuery()->where('quote_lines.quote_id', $quoteId));
        });
    }

    /**
     * Work orders with at least one source offer (`quote_id`, or the offers of
     * its pivot lines, spec 0267) located in one of the given operational
     * sites. A NULL site never matches; an empty list matches nothing.
     *
     * @param  Builder<WorkOrder>  $query
     * @param  array<int, int>  $siteIds
     */
    public function scopeWithSourceQuoteInSites(Builder $query, array $siteIds): void
    {
        $query->where(function (Builder $query) use ($siteIds): void {
            $query->whereExists(
                DB::table('quotes')
                    ->whereColumn('quotes.id', 'work_orders.quote_id')
                    ->whereIn('quotes.operational_site_id', $siteIds)
                    ->select(DB::raw(1))
            )->orWhereExists(
                self::pivotLinesQuery()
                    ->join('quotes', 'quotes.id', '=', 'quote_lines.quote_id')
                    ->whereIn('quotes.operational_site_id', $siteIds)
            );
        });
    }

    /**
     * Work orders of a registry (customer), directly or through one of their
     * lines' offers (quotes.opportunity_id -> opportunities.registry_id).
     *
     * @param  Builder<WorkOrder>  $query
     */
    public function scopeWithQuoteLineOfRegistry(Builder $query, int $registryId): void
    {
        $query->where(function (Builder $query) use ($registryId): void {
            $query->whereExists(
                DB::table('quotes')
                    ->join('opportunities', 'opportunities.id', '=', 'quotes.opportunity_id')
                    ->whereColumn('quotes.id', 'work_orders.quote_id')
                    ->where('opportunities.registry_id', $registryId)
                    ->select(DB::raw(1))
            )->orWhereExists(
                self::pivotLinesQuery()
                    ->join('quotes', 'quotes.id', '=', 'quote_lines.quote_id')
                    ->join('opportunities', 'opportunities.id', '=', 'quotes.opportunity_id')
                    ->where('opportunities.registry_id', $registryId)
            );
        });
    }

    private static function pivotLinesQuery(): QueryBuilder
    {
        return DB::table('quote_line_work_order')
            ->join('quote_lines', 'quote_lines.id', '=', 'quote_line_work_order.quote_line_id')
            ->whereColumn('quote_line_work_order.work_order_id', 'work_orders.id')
            ->select(DB::raw(1));
    }

    /**
     * The internal Users accountable for this commessa — "Responsabili" in
     * the UI (spec 0096, D-1). MANY, possibly none (user decision
     * 2026-10-20).
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
     * The proforma requests raised for this commessa (spec 0193).
     *
     * @return HasMany<ProformaRequest, $this>
     */
    public function proformaRequests(): HasMany
    {
        return $this->hasMany(ProformaRequest::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<PurchaseOrder, $this>
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /**
     * @return HasMany<PurchaseOrderLine, $this>
     */
    public function purchaseOrderLines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    /**
     * @return HasMany<PurchaseInvoice, $this>
     */
    public function purchaseInvoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class);
    }

    /**
     * Adds one EXISTS flag per accounting relation (no query per row). Read
     * back through hasAccountingDocuments(); spec 0243, D-1.
     *
     * @param  Builder<WorkOrder>  $query
     */
    public function scopeWithAccountingDocumentFlags(Builder $query): void
    {
        $query->withExists(array_map(
            static fn (string $relation): string => "{$relation} as ".self::accountingFlag($relation),
            self::ACCOUNTING_RELATIONS,
        ));
    }

    /**
     * True when any proforma request, active invoice, purchase order (header
     * or line) or purchase invoice references this commessa. Uses the flags
     * loaded by scopeWithAccountingDocumentFlags() when present, else queries.
     */
    public function hasAccountingDocuments(): bool
    {
        foreach (self::ACCOUNTING_RELATIONS as $relation) {
            $flag = self::accountingFlag($relation);
            $present = array_key_exists($flag, $this->attributes)
                ? (bool) $this->attributes[$flag]
                : $this->{$relation}()->exists();

            if ($present) {
                return true;
            }
        }

        return false;
    }

    private static function accountingFlag(string $relation): string
    {
        return 'has_accounting_'.$relation;
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

    /**
     * The Modello di Task this commessa was generated from, if any (spec
     * 0124, D-5): null for a commessa created without one (AC-020).
     * `restrictOnDelete` at the schema — a template referenced here cannot
     * be removed, only deactivated (D-5, AC-011).
     *
     * @return BelongsTo<TaskTemplate, $this>
     */
    public function taskTemplate(): BelongsTo
    {
        return $this->belongsTo(TaskTemplate::class);
    }

    /**
     * The ordered "Fasi" of this commessa's task board (spec 0146, D-2):
     * copied from the `TaskTemplate` at generation time, then managed
     * independently (create/rename/reorder/close/reopen/delete).
     *
     * @return HasMany<WorkOrderStage, $this>
     */
    public function stages(): HasMany
    {
        return $this->hasMany(WorkOrderStage::class)->orderBy('sort_order');
    }

    /**
     * Every Task linked to this commessa, root and sub-task alike (spec
     * 0146) — the inverse of `Task::workOrder()`. The task board's own
     * ordering (fase, `stage_position`) is applied by its query, not here.
     *
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * The ACTUAL costs entered on this commessa (spec 0190), in grid order.
     *
     * @return HasMany<WorkOrderCost, $this>
     */
    public function costs(): HasMany
    {
        return $this->hasMany(WorkOrderCost::class)->orderBy('sort_order');
    }

    /**
     * The manual progress records of this commessa (spec 0321), in insertion
     * order. Each holds an INCREMENT; the current progress is their SUM.
     *
     * @return HasMany<WorkOrderProgressEntry, $this>
     */
    public function progressEntries(): HasMany
    {
        return $this->hasMany(WorkOrderProgressEntry::class)->orderBy('id');
    }

    /**
     * The per-line payment data of this commessa (spec 0201).
     *
     * @return HasMany<WorkOrderLinePayment, $this>
     */
    public function linePayments(): HasMany
    {
        return $this->hasMany(WorkOrderLinePayment::class);
    }

    /**
     * This commessa's email history and drafts (spec 0175, D-2/D-10): every
     * `OutboundEmail` sent or drafted from its "Email" tab, newest first is
     * the caller's own concern — no default ordering here.
     *
     * @return MorphMany<OutboundEmail, $this>
     */
    public function outboundEmails(): MorphMany
    {
        return $this->morphMany(OutboundEmail::class, 'emailable');
    }
}
