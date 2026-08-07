<?php

namespace App\Http\Resources;

use App\Enums\FormMode;
use App\Models\Opportunity;
use App\Models\Quote;
use App\RequestManagement\ApplicableAttribute;
use App\RequestManagement\RequestAttributeResolver;
use App\Services\Opportunities\OpportunityManagerLabelResolver;
use App\Services\Opportunities\OpportunityStatusResolver;
use App\Services\Quotes\QuoteWorkflowResolver;
use App\Support\Geo\GeoNameLocalizer;
use App\Support\OperationalSiteLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Attributes\PreserveKeys;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wire shape for the request-management work panel (spec 0049,
 * data_contract GET/PATCH/POST /api/request-management/{quote}, migrated
 * onto the Quote by spec 0086 D-2). Consumes the {quote} array
 * RequestManagementService::loadWorkPanel()/updateWork() build — never a raw
 * Quote/QuoteResource: this is a DEDICATED, purpose-built shape for the
 * operative panel (contacts owners, read-only context), independent from the
 * quotes CRUD resource.
 *
 * Spec 0086, D-2/D-9: `id`/`opportunity_id` and every field the panel already
 * carried are re-sourced per the data_contract — the opportunity-level blocks
 * (identity, attribution's Fonte, product lines, context) read through
 * `quote.opportunity`, while `reporter`/`operational_site`/`is_transferred`/
 * `transferred_from`/`operator` (still wired to `operator_id`/`operator` on
 * the wire) and `rewards` come straight off the Quote. `products_of_interest`
 * is REPLACED by `offer_lines` (AC-021), the Quote's own REVENUE lines —
 * projected by QuoteLineResource verbatim since the user directive
 * 2026-08-07 made them editable from this panel too.
 *
 * User directive 2026-08-07: two blocks the module had lost to specs 0083 D-2
 * / 0084 D-1 come back, now sourced from the Offerta — `attribute_values`/
 * `applicable_attributes`/`attribute_layout` ("Informazioni aggiuntive",
 * applicable set per RequestAttributeResolver D-1) and the
 * `quote_workflow_status_id`/`quote_workflow_status`/`quote_workflow_statuses`
 * triplet ("Stato di lavorazione"). Both are byte-for-byte the projections
 * QuoteResource exposes, so the two forms render the identical components off
 * the identical types.
 *
 * `client_contacts`/`referent_contacts` expose an `owner` OwnerRef
 * (`{type: 'personal_data', id}`) alongside the contact `items`, so the
 * frontend's ContactsManager can persist directly against the PersonalData
 * card (D-6) without re-deriving the owner from the opportunity. Registry/
 * Referent are NOT valid `contactable_type`s (`config/personal_data.php`
 * `contactable_types` lists only `personal_data`), so the ref must point at
 * the PersonalData card, never the entity.
 *
 * #[PreserveKeys]: `manager_labels` (spec 0080) is a sparse
 * position("1".."4")->label map — JsonResource's default filter() reindexes
 * any NESTED array whose keys are ALL numeric, which would silently turn
 * `{"2":"Operatore"}` into `["Operatore"]` on the wire. Every other array
 * field here is already 0-indexed-sequential, so this is a no-op for them.
 */
#[PreserveKeys]
class RequestManagementResource extends JsonResource
{
    /**
     * @param  array{quote: Quote}  $resource
     */
    public function __construct(array $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Quote $quote */
        $quote = $this->resource['quote'];
        /** @var Opportunity $opportunity */
        $opportunity = $quote->opportunity;

        return [
            'id' => $quote->id,
            // Spec 0086, D-9: the collaborative record's own identifier —
            // documents/notes/activity row actions key off this, never `id`.
            'opportunity_id' => $opportunity->id,
            'name' => $opportunity->name,
            'registry' => $this->summarizeByName($opportunity->registry),
            'referent' => $this->summarizeByName($opportunity->referent),
            'commercial' => $this->summarizeByName($opportunity->commercial),
            // Attribution block (user directive 2026-07-22): editable from
            // this panel, so each one ships BOTH its id (the form's value)
            // and its `{id, name}` projection (the picker's hydration).
            // "Fonte" stays on the Opportunity (D-2); "Segnalatore" and the
            // Sede operativa moved to the Quote (see below).
            'source_id' => $opportunity->source_id,
            'source' => $this->summarizeByName($opportunity->source),
            'reporter_id' => $quote->reporter_id,
            'reporter' => $this->summarizeByName($quote->reporter),
            // Spec 0056: the Sede operativa, editable from this same
            // attribution block — the site has no own name, so its ref is
            // {id, label} (OperationalSiteLabel), NOT summarizeByName().
            'operational_site_id' => $quote->operational_site_id,
            'operational_site' => OperationalSiteLabel::summarize($quote->operationalSite),
            // Spec 0079/0086 D-6: the transfer flag + the origin Sede's
            // label, both read-only outputs, now per-Offerta — two sibling
            // quotes of the same opportunity carry independent values.
            'is_transferred' => (bool) $quote->is_transferred,
            'transferred_from' => OperationalSiteLabel::summarize($quote->transferredFromOperationalSite),
            // Wire keys stay `operator_id`/`operator` (D-2: the frontend
            // still calls it "operatore"); the value is the Offerta's own
            // Supervisore (AC-020).
            'operator_id' => $quote->supervisor_id,
            'operator' => $this->summarizeByName($quote->supervisor),
            // Spec 0080: ADDITIVE — the "Gestore Account" label overrides
            // resolved from the opportunity's product line(s), so the panel
            // can rietichettare "Operatore (GA2)" with the level-2 label when
            // the category defines one. `{}` when not resolvable.
            'manager_labels' => app(OpportunityManagerLabelResolver::class)->resolve($opportunity),
            'status' => app(OpportunityStatusResolver::class)->resolve($opportunity),
            'product_lines' => $this->summarizeProductLines($opportunity->productLines),
            // Spec 0086, D-7/AC-021: replaces `products_of_interest` — the
            // Offerta's own REVENUE lines. EDITABLE from this module since the
            // user directive 2026-08-07, so the projection is the Offerte
            // module's own QuoteLineResource verbatim: the row editor is the
            // same component, and it needs the same row (quantity, prezzo
            // unitario, aliquota, importi congelati), not a name-only summary.
            'offer_lines' => QuoteLineResource::collection($quote->offerLines),
            'client_identity' => $this->summarizeClientIdentity($opportunity->registry),
            'client_contacts' => $this->summarizeContacts($opportunity->registry),
            'client_address' => $this->summarizeClientAddress($opportunity->registry),
            'referent_contacts' => $this->summarizeContacts($opportunity->referent),
            'next_callback_at' => $opportunity->next_callback_at?->format('Y-m-d\TH:i'),
            // "Informazioni aggiuntive" (user directive 2026-08-07): the same
            // three blocks QuoteResource exposes, byte-for-byte — only the
            // applicable set is resolved by RequestAttributeResolver (D-1,
            // product lines UNION offer lines). The values map itself is the
            // Offerta's own `quotes.attribute_values`: one storage, two forms.
            'attribute_values' => (object) ($quote->attribute_values ?? []),
            'applicable_attributes' => $this->resolveApplicableAttributes($quote),
            'attribute_layout' => app(RequestAttributeResolver::class)->layout($quote, FormMode::Edit),
            // "Stato di lavorazione" (user directive 2026-08-07): the Offerta's
            // own operational status (spec 0083), advanced from this panel too.
            // Same triplet as QuoteResource — current id, its projection, and
            // the full set QuoteWorkflowResolver resolves right now, which is
            // what limits the select (AC-021/050).
            'quote_workflow_status_id' => $quote->quote_workflow_status_id,
            'quote_workflow_status' => $this->summarizeWorkflowStatus($quote->quoteWorkflowStatus),
            'quote_workflow_statuses' => $this->resolveWorkflowStatuses($quote),
            'context' => [
                'estimated_value' => $opportunity->estimated_value,
                'expected_close_date' => $opportunity->expected_close_date?->format('Y-m-d'),
                'success_probability' => $opportunity->success_probability,
                // "Note generali" (user directive 2026-07-27): READ-ONLY here.
                // It joins the context block rather than the editable fields
                // above because this module never writes it — the opportunity
                // form owns that, mirroring D-5's treatment of the sales
                // dimensions.
                'general_notes' => $opportunity->general_notes,
            ],
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function summarizeByName(?Model $related): ?array
    {
        return $related === null ? null : ['id' => $related->id, 'name' => $related->name];
    }

    /**
     * The "Informazioni aggiuntive" descriptors for THIS request (D-1).
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolveApplicableAttributes(Quote $quote): array
    {
        return app(RequestAttributeResolver::class)
            ->resolve($quote)
            ->map(fn (ApplicableAttribute $attribute): array => $attribute->toArray())
            ->values()
            ->all();
    }

    /**
     * The currently resolved working-state row (spec 0083, D-1/D-8), same
     * projection as QuoteResource's.
     *
     * @return array{id: int, name: string, color: string|null, group: string, requires_note: bool}|null
     */
    private function summarizeWorkflowStatus(?Model $status): ?array
    {
        return $status === null ? null : [
            'id' => $status->id,
            'name' => $status->name,
            'color' => $status->color,
            'group' => $status->group->value,
            'requires_note' => $status->requires_note,
        ];
    }

    /**
     * The full ordered set QuoteWorkflowResolver resolves for this Offerta
     * RIGHT NOW — the only rows the panel's select may offer (AC-021/050).
     * Carries `sort_order` like the quotes contract does.
     *
     * @return array<int, array{id: int, name: string, color: string|null, group: string, requires_note: bool, sort_order: int}>
     */
    private function resolveWorkflowStatuses(Quote $quote): array
    {
        $resolver = app(QuoteWorkflowResolver::class);

        return $resolver->statusesFor($resolver->resolve($quote))
            ->map(fn (Model $status): array => [
                ...$this->summarizeWorkflowStatus($status),
                'sort_order' => $status->sort_order,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, business_function: array{id: int, name: string}|null, product_category: array{id: int, name: string}|null}>
     */
    private function summarizeProductLines(iterable $lines): array
    {
        return collect($lines)
            ->map(fn (Model $line): array => [
                'id' => $line->id,
                'business_function' => $this->summarizeByName($line->businessFunction),
                'product_category' => $this->summarizeByName($line->productCategory),
            ])
            ->all();
    }

    /**
     * The `client_contacts`/`referent_contacts` block (D-6): the OwnerRef the
     * frontend's ContactsManager persists against, plus the owner's current
     * contact channels via PersonalData (HasPersonalData -> HasContacts).
     * `owner` MUST reference the PersonalData card itself — Registry/Referent
     * are not valid `contactable_type`s (`config/personal_data.php`
     * `contactable_types` => ['personal_data' => PersonalData]) — so
     * ContactsManager's create/update writes land on the right
     * `contactable_type`/`contactable_id`. `null` when the entity has no
     * PersonalData card.
     *
     * @return array{owner: array{type: 'personal_data', id: int}|null, items: AnonymousResourceCollection|array<int, mixed>}
     */
    private function summarizeContacts(?Model $entity): array
    {
        $personalData = $entity?->personalData;

        if ($personalData === null) {
            return ['owner' => null, 'items' => []];
        }

        return [
            'owner' => ['type' => 'personal_data', 'id' => $personalData->id],
            'items' => ContactResource::collection($personalData->contacts),
        ];
    }

    /**
     * The client's IDENTITY fields, the top block of the panel's "anagrafica"
     * section: who the client is (individual vs company) and the fiscal
     * identifiers the operator has to capture (tax code, VAT number, SDI).
     *
     * PRIVACY NOTE: `tax_code`, `vat_number` and `birth_date` are $hidden on
     * PersonalData and re-exposed here on purpose, exactly as
     * PersonalDataResource does for the identity sheet — property access
     * bypasses $hidden, so this projection is the re-exposure point. It is
     * reachable only through `request-management.view` plus the D-3 scope
     * guard (RequestManagementScope), the same gate as the rest of the panel.
     *
     * `null` when the client has no card yet: there is nothing to show, and the
     * panel hides the block rather than offering fields no write path accepts.
     *
     * @return array<string, mixed>|null
     */
    private function summarizeClientIdentity(?Model $entity): ?array
    {
        $card = $entity?->personalData;

        if ($card === null) {
            return null;
        }

        return [
            'id' => $card->id,
            'type' => $card->type->value,
            'first_name' => $card->first_name,
            'last_name' => $card->last_name,
            'company_name' => $card->company_name,
            'tax_code' => $card->tax_code,
            'vat_number' => $card->vat_number,
            'sdi_code' => $card->sdi_code,
            'birth_date' => $card->birth_date?->format('Y-m-d'),
            'birth_city_id' => $card->birth_city_id,
            'birth_city' => $card->relationLoaded('birthCity') && $card->birthCity !== null
                ? ['id' => $card->birthCity->id, 'name' => GeoNameLocalizer::toItalian($card->birthCity->name)]
                : null,
            'residence_city_id' => $card->residence_city_id,
            'residence_city' => $card->relationLoaded('residenceCity') && $card->residenceCity !== null
                ? ['id' => $card->residenceCity->id, 'name' => GeoNameLocalizer::toItalian($card->residenceCity->name)]
                : null,
            'gender' => $card->gender?->value,
        ];
    }

    /**
     * The client's PRIMARY address, the single row the panel's "anagrafica"
     * section edits inline (spec 0049 amendment). Falls back to the card's
     * first address for legacy rows left without a primary flag, and stays
     * null when the client has no card or no address yet — the section then
     * starts blank and a save creates the first one.
     */
    private function summarizeClientAddress(?Model $entity): ?AddressResource
    {
        $addresses = $entity?->personalData?->addresses;

        if ($addresses === null) {
            return null;
        }

        $address = $addresses->firstWhere('is_primary', true) ?? $addresses->first();

        return $address === null ? null : new AddressResource($address);
    }
}
