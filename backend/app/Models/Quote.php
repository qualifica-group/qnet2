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

/**
 * Quote entity (spec 0065): a preventivo belonging to exactly one Opportunity
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
 */
#[Fillable([
    'title',
    'opportunity_id',
    'quote_status_id',
    'commercial_id',
    'reporter_id',
    'supervisor_id',
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
     * The quote's working-state classification (spec 0065, D-2): mandatory,
     * restrictOnDelete, defaulted server-side to the system 'new' row when
     * omitted (AC-023).
     */
    public function quoteStatus(): BelongsTo
    {
        return $this->belongsTo(QuoteStatus::class);
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
}
