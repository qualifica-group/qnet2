<?php

namespace App\Http\Resources;

use App\Models\Opportunity;
use App\RequestManagement\ApplicableAttribute;
use App\Services\Opportunities\OpportunityManagerLabelResolver;
use App\Services\Opportunities\OpportunityStatusResolver;
use App\Support\Geo\GeoNameLocalizer;
use App\Support\OperationalSiteLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Attributes\PreserveKeys;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Wire shape for the request-management work panel (spec 0049,
 * data_contract GET/PATCH /api/request-management/{opportunity}). Consumes
 * the {opportunity, applicable_attributes, attribute_layout} array
 * RequestManagementService::loadWorkPanel()/updateWork() build — never a raw
 * Opportunity/OpportunityResource: this is a DEDICATED, purpose-built shape
 * for the operative panel (contacts owners, applicable_attributes,
 * read-only context), independent from the opportunities CRUD resource
 * (D-1/constraints: no change to OpportunityResource's own contract here).
 * Spec 0083, D-2: this panel no longer advances any working status of its
 * own — `workflow_status`/`workflow_statuses` are GONE, `status` stays the
 * COMPUTED, read-only summary (OpportunityStatusResolver) it already was.
 *
 * `client_contacts`/`referent_contacts` expose an `owner` OwnerRef
 * (`{type: 'personal_data', id}`) alongside the contact `items`, so the
 * frontend's ContactsManager can persist directly against the PersonalData
 * card (D-6) without re-deriving the owner from the opportunity. Registry/
 * Referent are NOT valid `contactable_type`s (`config/personal_data.php`
 * `contactable_types` lists only `personal_data`), so the ref must point at
 * the PersonalData card, never the entity.
 *
 * `attribute_layout` (spec 0062) is additive: the merged, multi-category
 * resolved layout (or null, flat fallback) — `applicable_attributes` stays
 * the untouched value-pipeline authority.
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
     * @param  array{opportunity: Opportunity, applicable_attributes: Collection<int, ApplicableAttribute>, attribute_layout: array<string, mixed>|null}  $resource
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
        /** @var Opportunity $opportunity */
        $opportunity = $this->resource['opportunity'];

        return [
            'id' => $opportunity->id,
            'name' => $opportunity->name,
            'registry' => $this->summarizeByName($opportunity->registry),
            'referent' => $this->summarizeByName($opportunity->referent),
            'commercial' => $this->summarizeByName($opportunity->commercial),
            // Attribution block (user directive 2026-07-22): editable from
            // this panel, so each one ships BOTH its id (the form's value)
            // and its `{id, name}` projection (the picker's hydration).
            'source_id' => $opportunity->source_id,
            'source' => $this->summarizeByName($opportunity->source),
            'reporter_id' => $opportunity->reporter_id,
            'reporter' => $this->summarizeByName($opportunity->reporter),
            // Spec 0056: the Sede operativa, editable from this same
            // attribution block — the site has no own name, so its ref is
            // {id, label} (OperationalSiteLabel), NOT summarizeByName().
            'operational_site_id' => $opportunity->operational_site_id,
            'operational_site' => OperationalSiteLabel::summarize($opportunity->operationalSite),
            // Spec 0079: the transfer flag + the origin Sede's label, both
            // read-only outputs — no form, inline-editor or endpoint of this
            // module accepts either in writing (AC-024).
            'is_transferred' => (bool) $opportunity->is_transferred,
            'transferred_from' => OperationalSiteLabel::summarize($opportunity->transferredFromOperationalSite),
            'operator_id' => $opportunity->operatorManager()?->id,
            'operator' => $this->summarizeByName($opportunity->operatorManager()),
            // Spec 0080: ADDITIVE — the "Gestore Account" label overrides
            // resolved from the request's product line(s), so the panel can
            // rietichettare "Operatore (GA2)" with the level-2 label when the
            // category defines one. `{}` when not resolvable.
            'manager_labels' => app(OpportunityManagerLabelResolver::class)->resolve($opportunity),
            'status' => app(OpportunityStatusResolver::class)->resolve($opportunity),
            'product_lines' => $this->summarizeProductLines($opportunity->productLines),
            'products_of_interest' => $this->summarizeProductsOfInterest($opportunity->productsOfInterest),
            'client_identity' => $this->summarizeClientIdentity($opportunity->registry),
            'client_contacts' => $this->summarizeContacts($opportunity->registry),
            'client_address' => $this->summarizeClientAddress($opportunity->registry),
            'referent_contacts' => $this->summarizeContacts($opportunity->referent),
            'applicable_attributes' => $this->summarizeApplicableAttributes($this->resource['applicable_attributes']),
            'attribute_layout' => $this->resource['attribute_layout'],
            'attribute_values' => $opportunity->attribute_values ?? [],
            'next_callback_at' => $opportunity->next_callback_at?->format('Y-m-d\TH:i'),
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
     * "Prodotti di interesse" (user directive 2026-07-22): the products the
     * operator recorded for this request, each with its own category so the
     * panel can show which product line it belongs to. Same shape as
     * OpportunityResource's, so the work panel and the opportunity card read
     * the collection identically.
     *
     * @return array<int, array{id: int, name: string, product_category: array{id: int, name: string}|null}>
     */
    private function summarizeProductsOfInterest(iterable $products): array
    {
        return collect($products)
            ->map(fn (Model $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'product_category' => $this->summarizeByName($product->category),
            ])
            ->values()
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
     * reachable only through `request-management.view` plus the GA2 scope
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

    /**
     * @param  Collection<int, ApplicableAttribute>  $attributes
     * @return array<int, array<string, mixed>>
     */
    private function summarizeApplicableAttributes(Collection $attributes): array
    {
        return $attributes
            ->map(fn (ApplicableAttribute $attribute): array => $attribute->toArray())
            ->values()
            ->all();
    }
}
