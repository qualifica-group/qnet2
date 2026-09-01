<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasFieldChangeRequests;
use App\Models\Concerns\HasNotes;
use App\Models\Concerns\HasRewards;
use App\Models\Concerns\LogsModelActivity;
use App\Support\ManagerPositions;
use Database\Factories\OpportunityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Opportunity entity (spec 0040): a commercial deal against an Anagrafica
 * (`registry`), created manually or generated from a `Lead` (`lead_id`,
 * nullable, UNIQUE — D-2: at most one opportunity per lead). `name`/
 * `registry_id` are mandatory (D-4); every other relation is optional.
 * `commercial`/`reporter` mirror Registry's own referent fields; `referent`
 * is NOT derivable (spec 0041 D-3), a plain always-editable field; `source`
 * is BR-1-derivable from the linked lead. Amendment rev.3: the former single
 * `business_function_id`/`product_category_id` columns are REPLACED by
 * `productLines()`, a one-to-many collection (see OpportunityProductLine).
 * User directive 2026-07-17: `company_id`/`company_site_id`/
 * `operational_site_id` and their relations are REMOVED entirely.
 * Spec 0082: the former `opportunity_status_id` FK is GONE — an Opportunity's
 * status is COMPUTED from its `quotes()` (falling back to the global default
 * quote-workflow `open` row when it has none, spec 0083 D-8) by
 * App\Services\Opportunities\OpportunityStatusResolver. Spec 0083, D-2: the
 * Opportunity no longer carries any working-state FK of its own either — its
 * former workflow-status override column and `workflowStatus()` relation are
 * GONE, the configurator having moved onto the Offerta (spec 0047 -> Quote).
 *
 * `general_notes` (user directive 2026-07-27): the "Note generali" free text,
 * inherited from the originating Lead's `notes` at conversion. Named
 * `general_notes`, NOT `notes`, because `notes()` is already this model's
 * collaborative-thread relation (HasNotes, spec 0052).
 *
 * Spec 0084: the former `attribute_values` column (the dynamic "Informazioni
 * aggiuntive" map) is GONE — that concern moved to the Offerta (Quote), one
 * per quote rather than one per opportunity.
 *
 * Spec 0056 (2026-07-23) SUPERSEDES the 2026-07-17 removal limitedly to
 * `operational_site_id`: reintroduced as a plain, optional FK (nullOnDelete —
 * a deliberate deviation from this model's other restrictOnDelete relations,
 * BR-3, since this one is optional). `company_id`/`company_site_id` stay
 * removed.
 */
#[Fillable([
    'name',
    'registry_id',
    'referent_id',
    'commercial_id',
    'reporter_id',
    'supervisor_id',
    'source_id',
    'operational_site_id',
    'lead_id',
    'start_date',
    'estimated_value',
    'expected_close_date',
    'success_probability',
    'general_notes',
])]
class Opportunity extends BaseModel
{
    /** @use HasFactory<OpportunityFactory> */
    use HasAttachments, HasFactory, HasFieldChangeRequests, HasNotes, HasRewards, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'expected_close_date' => 'date',
            'estimated_value' => 'decimal:2',
            'success_probability' => 'integer',
            'next_callback_at' => 'datetime',
            'next_callback_reminded_at' => 'datetime',
            'is_transferred' => 'boolean',
        ];
    }

    public function registry(): BelongsTo
    {
        return $this->belongsTo(Registry::class);
    }

    /**
     * The contact person driving the deal. NOT derivable (spec 0041 D-3): a
     * plain, always-editable field scoped to the chosen registry (BR-4).
     */
    public function referent(): BelongsTo
    {
        return $this->belongsTo(Referent::class);
    }

    public function commercial(): BelongsTo
    {
        return $this->belongsTo(Referent::class, 'commercial_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Referent::class, 'reporter_id');
    }

    /**
     * The internal user supervising the deal ("Supervisore") — an employee,
     * not an external referent, mirroring Registry::supervisor().
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /**
     * The Sede operativa (spec 0056): a plain, optional FK, editable from the
     * form and from the Gestione Richieste "Lavora" panel, with no server-side
     * derivation/lock (unlike `lead`/BR-1's fields). `nullOnDelete`: losing
     * the referenced site clears the field rather than blocking the site's
     * deletion.
     */
    public function operationalSite(): BelongsTo
    {
        return $this->belongsTo(OperationalSite::class);
    }

    /**
     * The Sede operativa a request was transferred FROM (spec 0079): null
     * when never transferred, or when the origin site has since been deleted
     * (`nullOnDelete` — `is_transferred` survives that, only the origin
     * reference and its detail-panel notice disappear). Written exclusively
     * by RequestTransferService, never mass-assignable (see #[Fillable]).
     */
    public function transferredFromOperationalSite(): BelongsTo
    {
        return $this->belongsTo(OperationalSite::class, 'transferred_from_operational_site_id');
    }

    /**
     * The funzione-aziendale + categoria-prodotto rows against this
     * opportunity (spec 0040, amendment rev.3): REPLACES the former single
     * `business_function_id`/`product_category_id` columns.
     *
     * @return HasMany<OpportunityProductLine, $this>
     */
    public function productLines(): HasMany
    {
        return $this->hasMany(OpportunityProductLine::class);
    }

    /**
     * The products the operator recorded as "di interesse" for this request
     * (user directive 2026-07-22): a plain reference collection, no pivot
     * payload — for now it only answers "which products is this request
     * about". Written exclusively by OpportunityProductInterestWriter, which
     * also guarantees every selected product's category is covered by a
     * `productLines()` row.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function productsOfInterest(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'opportunity_product')->orderBy('products.name');
    }

    /**
     * The lead this opportunity was generated from, if any (BR-1/D-2:
     * nullable, unique — read-only server-side derivation lock, see
     * OpportunityService/LeadOpportunityDefaultsResolver).
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * The Account Manager pivot `position` that designates the "Operatore"
     * (GA2) — the operative owner the request-management module (spec 0049)
     * used both for the "my requests" scope and the "Operatore" column,
     * before spec 0087 moved that ownership to the Offerta's own GA2
     * (`Quote::managers()`/`quotes.operator_id`).
     *
     * ALIAS of `ManagerPositions::OPERATOR` (spec 0087, D-2), not renamed:
     * this constant keeps its own name so every one of its 15 existing call
     * sites stays untouched (engineering.md §1.6, blast radius). `Quote`
     * reads `ManagerPositions::OPERATOR` directly instead of this alias.
     */
    public const int OPERATOR_MANAGER_POSITION = ManagerPositions::OPERATOR;

    /**
     * The internal users managing this opportunity ("Gestori Account", max
     * App\Support\ManagerPositions::MAX — validation-layer only, see
     * StoreOpportunityRequest), mirroring
     * Registry::managers() verbatim (spec 0040, "ranking inherited by future
     * modules like Opportunities" per the 2026_07_13_130000 docblock).
     */
    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'opportunity_user')
            ->withPivot('position')
            ->orderByPivot('position');
    }

    /**
     * The GA2 "Operatore" — the manager at pivot position
     * OPERATOR_MANAGER_POSITION — resolved from the already-loaded collection
     * when available, otherwise with an EXPLICIT query (never a lazy-loaded
     * relation access, so Model::preventLazyLoading() stays satisfied on a
     * bare route-bound model). The one place the "position 2 = operator" rule
     * is expressed for reads; RequestRowMapper's own copy stays as-is because
     * it projects the grid's avatar bag from an eager-loaded page.
     */
    public function operatorManager(): ?User
    {
        if ($this->relationLoaded('managers')) {
            return $this->managers->first(
                static fn (User $manager): bool => (int) $manager->pivot->position === self::OPERATOR_MANAGER_POSITION,
            );
        }

        return $this->managers()->wherePivot('position', self::OPERATOR_MANAGER_POSITION)->first();
    }

    /**
     * `operator_id`: the GA2 operator's user id as a virtual attribute. It is
     * NOT a column — the value lives on the `opportunity_user` pivot — but the
     * request-management panel writes it as a single field, so the generic
     * field-permission diff (EnforcesFieldPermissions reads every catalogue
     * field off the model) needs it readable by that name.
     */
    protected function operatorId(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->operatorManager()?->id);
    }

    /**
     * The Quotes belonging to this opportunity (spec 0065/0067): the
     * explicit inverse of `Quote::opportunity()`. The FK is already
     * `restrictOnDelete` at the schema level, so an opportunity with at
     * least one quote stays non-deletable regardless of this relation.
     *
     * @return HasMany<Quote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }
}
