<?php

namespace App\Http\Resources;

use App\Models\Address;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lead
 *
 * `operational_site` has no own name column (BR-3, mirrors
 * OperationalSiteForSelectResource): its label is composed from the site's
 * primary address `line1` plus " - {city}" when present. Relies on
 * LeadService::loadDetail() having eager-loaded `operationalSite.addresses.city`,
 * so resolving it here never N+1s.
 *
 * Spec 0047 (AC-003): `state`/`state_id` is the Regione (D1), derived
 * server-side from the sede — never user-editable directly.
 *
 * Spec 0094 (AC-030): `products_of_interest` mirrors
 * OpportunityResource::summarizeProductsOfInterest() verbatim. Relies on
 * LeadService::loadDetail() having eager-loaded `productsOfInterest.category`,
 * so resolving it here never N+1s.
 */
class LeadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'registry_id' => $this->registry_id,
            'registry' => $this->summarizeByName($this->registry),
            'campaign_id' => $this->campaign_id,
            'campaign' => $this->summarizeCampaign($this->campaign),
            'operational_site_id' => $this->operational_site_id,
            'operational_site' => $this->summarizeOperationalSite($this->operationalSite),
            'state_id' => $this->state_id,
            'state' => $this->summarizeByName($this->state),
            'source_id' => $this->source_id,
            'source' => $this->summarizeByName($this->source),
            'operator_id' => $this->operator_id,
            'operator' => $this->summarizeByName($this->operator),
            'lead_status' => $this->lifecycleStatus()->value,
            'notes' => $this->notes,
            'extra_fields' => $this->extra_fields,
            'products_of_interest' => $this->summarizeProductsOfInterest($this->productsOfInterest),
            // spec 0040: the opportunity generated from this lead, if any
            // (D-2: at most one). The lead itself carries no flag/column for
            // this (D-5) — presence is derived purely from the relation.
            'opportunity' => $this->summarizeOpportunity($this->opportunity),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function summarizeByName(?Model $related): ?array
    {
        if ($related === null) {
            return null;
        }

        return ['id' => $related->id, 'name' => $related->name];
    }

    /**
     * @return array{id: int, code: string, name: string}|null
     */
    private function summarizeCampaign(mixed $campaign): ?array
    {
        if ($campaign === null) {
            return null;
        }

        return ['id' => $campaign->id, 'code' => $campaign->code, 'name' => $campaign->name];
    }

    /**
     * @return array{id: int, label: string}|null
     */
    private function summarizeOperationalSite(mixed $site): ?array
    {
        if ($site === null) {
            return null;
        }

        /** @var Address|null $address */
        $address = $site->addresses->first();

        return ['id' => $site->id, 'label' => $this->composeSiteLabel($address)];
    }

    private function composeSiteLabel(?Address $address): string
    {
        if ($address === null) {
            return '';
        }

        $city = $address->city?->localizedName();

        return $city === null ? (string) $address->line1 : "{$address->line1} - {$city}";
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function summarizeOpportunity(mixed $opportunity): ?array
    {
        return $opportunity === null ? null : ['id' => $opportunity->id, 'name' => $opportunity->name];
    }

    /**
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
}
