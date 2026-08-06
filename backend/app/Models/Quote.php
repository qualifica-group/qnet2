<?php

namespace App\Models;

use App\Enums\QuoteLineType;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Quote entity (spec 0065): a quote belonging to exactly one Opportunity
 * (D-27/AC-027: an Opportunity with at least one Quote is not deletable).
 * `code` (`QUO-{seq:4}`, D-13, same manual/sequential pattern as
 * `Product::code`/D-1b) is DELIBERATELY absent from #[Fillable]: the service
 * assigns it after mass-assignment, inside the create transaction, exactly
 * like `Project`/`Campaign::code`. The five aggregate columns
 * (`revenue_net`, `revenue_vat`, `cost_net`, `cost_vat`, `margin_net`) are
 * likewise absent from #[Fillable] (D-9): they are PERSISTED but written only
 * by the quote service, recalculated from `quote_lines` on every write —
 * never client input.
 *
 * `commercial_id`/`reporter_id`/`supervisor_id` are a deliberate SNAPSHOT
 * (D-3): copied from the Opportunity at creation time by the service, then
 * independently editable — no live read-through, no re-sync when the
 * Opportunity's own values change later.
 *
 * `company_id`/`company_site_id`/`operational_site_id` (user directive
 * 2026-07-30) are all optional, nullOnDelete. `operational_site_id` joins the
 * D-3 snapshot set (prefilled from the Opportunity when the client omits it);
 * the other two have no Opportunity counterpart and are always picked by
 * hand. A `company_site` must belong to the quote's `company` — enforced at
 * the request layer (ValidatesQuoteCompanySite), not by the schema.
 *
 * `layout_id` (spec 0070, D-3/D-8) is the Document Layout used to generate
 * the preventivo's `.docx`, nullOnDelete. It is NOT part of the Opportunity
 * snapshot set above: `opportunities` has no `layout_id` column at all, and
 * this one is defaulted from `App\Services\DocumentLayouts\DocumentLayoutDefaultManager`'s
 * module default (QuoteService), a wholly separate mechanism (D-8).
 *
 * `payment_method_id` (user directive 2026-07-30) is the agreed payment
 * modality, nullOnDelete. It has neither an Opportunity counterpart to
 * inherit from nor a module default to resolve: a plain optional FK, written
 * only when the client submits it.
 *
 * `quote_workflow_status_id` (spec 0083, D-1/D-8 — replaces the former flat
 * quote-status pick) is DELIBERATELY absent from #[Fillable], mirroring the
 * Opportunity's own former workflow-status-override precedent: mandatory,
 * restrictOnDelete, always written by App\Services\QuoteService via
 * App\Services\Quotes\QuoteWorkflowResolver — an explicit, already-validated
 * client override is assigned verbatim, otherwise the resolver derives the
 * `open` row of the criteria-resolved set (AC-020/021/022).
 *
 * `attribute_values` (spec 0084): the dynamic "Informazioni aggiuntive" map,
 * moved here from the Opportunity — resolved from the categories of THIS
 * quote's own offer lines (App\Quotes\QuoteAttributeResolver), never the
 * parent Opportunity's product lines. DELIBERATELY absent from #[Fillable]
 * (mass-assignment guard, same discipline as the former
 * `opportunities.attribute_values`): written only by
 * App\Services\Quotes\QuoteAttributeValueWriter, inside QuoteService's
 * create/update transaction.
 */
#[Fillable([
    'title',
    'opportunity_id',
    'commercial_id',
    'reporter_id',
    'supervisor_id',
    'company_id',
    'company_site_id',
    'operational_site_id',
    'layout_id',
    'payment_method_id',
    'internal_notes',
])]
class Quote extends BaseModel
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revenue_net' => 'decimal:2',
            'revenue_vat' => 'decimal:2',
            'cost_net' => 'decimal:2',
            'cost_vat' => 'decimal:2',
            'margin_net' => 'decimal:2',
            'attribute_values' => 'array',
        ];
    }

    /**
     * The Opportunity this quote belongs to (restrictOnDelete, immutable
     * after creation — AC-025).
     */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    /**
     * The offer's working-state classification (spec 0047/0083, D-1/D-8):
     * mandatory, restrictOnDelete, resolved server-side against the
     * criteria-matched workflow set (or the global default) when omitted
     * (AC-020).
     */
    public function quoteWorkflowStatus(): BelongsTo
    {
        return $this->belongsTo(QuoteWorkflowStatus::class);
    }

    /**
     * The snapshot Referent commercial (D-3), via its own FK.
     */
    public function commercial(): BelongsTo
    {
        return $this->belongsTo(Referent::class, 'commercial_id');
    }

    /**
     * The snapshot Referent reporter/segnalatore (D-3), via its own FK.
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Referent::class, 'reporter_id');
    }

    /**
     * The snapshot internal User supervisor (D-3), via its own FK.
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    /**
     * The issuing Societa' (spec 0010 — "Societa' aziendali"), optional.
     * Its display name is the `denomination` column: `companies` has no
     * `name`.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The issuing Societa' Sede (spec 0020), optional. Constrained at the
     * request layer to a site of `company` when both are set.
     */
    public function companySite(): BelongsTo
    {
        return $this->belongsTo(CompanySite::class);
    }

    /**
     * The Sede operativa (spec 0011), optional. Snapshotted from the
     * Opportunity at creation when the client omits it (D-3 set); the site
     * has no own name — its identity is its primary address
     * (OperationalSiteLabel).
     */
    public function operationalSite(): BelongsTo
    {
        return $this->belongsTo(OperationalSite::class);
    }

    /**
     * The Document Layout used to generate this quote's `.docx` (spec 0070).
     * Resolved to the module's default at creation when omitted, then freely
     * editable — never re-synced (same snapshot-then-diverge shape as the
     * commercial roles, but NOT sourced from the Opportunity, D-8).
     */
    public function layout(): BelongsTo
    {
        return $this->belongsTo(DocumentLayout::class);
    }

    /**
     * The agreed payment modality (spec 0068's lookup, user directive
     * 2026-07-30). Optional and never defaulted: unlike `layout_id` there is
     * no "module default" concept on `payment_methods`, so an omitted key
     * simply leaves it null.
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * The Contract lifecycle data for this quote (spec 0072): exists only
     * once the quote reaches a `closed_won` status (D-6's automation),
     * one-to-one, never created/deleted by hand.
     *
     * @return HasOne<Contract, $this>
     */
    public function contract(): HasOne
    {
        return $this->hasOne(Contract::class);
    }

    /**
     * Every line, both revenue and cost, ordered for the Offerta/Costi tabs
     * read path (AC-038).
     *
     * @return HasMany<QuoteLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class)->orderBy('sort_order');
    }

    /**
     * The Offerta tab's rows (`line_type` = REVENUE), ordered by
     * `sort_order` — eager-loadable via `with('offerLines')` like any other
     * constrained HasMany.
     *
     * @return HasMany<QuoteLine, $this>
     */
    public function offerLines(): HasMany
    {
        return $this->hasMany(QuoteLine::class)
            ->where('line_type', QuoteLineType::Revenue)
            ->orderBy('sort_order');
    }

    /**
     * The Costi tab's rows (`line_type` = COST), ordered by `sort_order` —
     * eager-loadable via `with('costLines')` like any other constrained
     * HasMany.
     *
     * @return HasMany<QuoteLine, $this>
     */
    public function costLines(): HasMany
    {
        return $this->hasMany(QuoteLine::class)
            ->where('line_type', QuoteLineType::Cost)
            ->orderBy('sort_order');
    }

    /**
     * The notes SCOPED to this Offerta (spec 0085, D-1). Deliberately NOT
     * `HasNotes`: the Offerta is not a notable entity — the note hangs off the
     * parent Opportunity's thread and this FK only narrows it. Exists to count
     * them per row (`withCount`); the thread itself is always read through the
     * Opportunity. Soft-deleted notes are excluded by `Note`'s own global scope.
     *
     * @return HasMany<Note, $this>
     */
    public function scopedNotes(): HasMany
    {
        return $this->hasMany(Note::class);
    }
}
