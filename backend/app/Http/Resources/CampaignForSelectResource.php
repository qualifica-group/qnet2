<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\Address;
use App\Models\Campaign;
use Illuminate\Http\Request;

/**
 * For-select projection of a Campaign (GET /api/campaigns/for-select, spec
 * 0024): label = name, subtitle = code. Feeds the Lead form's campaign field
 * (the only consumer today — Campaign itself has no for-select of its own
 * before this spec, per the 0023 note). `meta.operational_site` (prefill-
 * modifiable sede) carries {id, label} — same shape ProjectForSelectResource
 * exposes — so the Lead form can prefill the Sede from the chosen campaign,
 * with no extra request. The Lead's Regione stays free/user-editable, never
 * auto-filled from the sede (user directive 2026-07-21): no state_id/
 * state_label here.
 *
 * Spec 0094, D-1/D-2: `meta.product_category_ids` is NEW — the EFFECTIVE
 * product-category ids (the linked project's when derived, else the
 * campaign's own), so the Lead form's "Prodotti di interesse" picker and the
 * import wizard filter `products/for-select` with no extra request. Relies on
 * CampaignService::forSelectBaseQuery() eager-loading `productLines`/
 * `project.productLines`.
 *
 * @mixin Campaign
 */
class CampaignForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
            'subtitle' => $this->code,
            'meta' => [
                'operational_site' => $this->summarizeOperationalSite($this->operationalSite),
                'product_category_ids' => $this->effectiveProductCategoryIds(),
            ],
        ];
    }

    /**
     * @return array<int, int>
     */
    private function effectiveProductCategoryIds(): array
    {
        $lines = $this->project !== null ? $this->project->productLines : $this->productLines;

        return $lines->pluck('product_category_id')->map(intval(...))->values()->all();
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
}
