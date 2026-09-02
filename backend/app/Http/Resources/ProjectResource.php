<?php

namespace App\Http\Resources;

use App\Enums\GeoScopeLevel;
use App\Models\Address;
use App\Models\Project;
use App\Support\Geo\GeoNameLocalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 *
 * `operational_site` (prefill-modifiable sede, no server-side inheritance)
 * has no own name column: its label is composed from the site's primary
 * address `line1` plus " - {city}" when present, the same composition
 * LeadResource/OperationalSiteForSelectResource use. Relies on
 * ProjectService::loadDetail() having eager-loaded
 * `operationalSite.addresses.city`.
 *
 * Spec 0094, D-1/D-2: `business_function_id`/`business_function`/
 * `product_category_id`/`product_category` are REPLACED by `product_lines`
 * (one row per funzione-aziendale + categoria-prodotto pair), mirroring
 * OpportunityResource's own amendment rev.3 shape. Relies on
 * ProjectService::DETAIL_RELATIONS eager-loading
 * `productLines.businessFunction`/`productLines.productCategory`.
 */
class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $totalBudget = $this->total_budget;
        $allocatedBudget = (float) ($this->allocated_budget_sum ?? 0);

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'pipeline_status_id' => $this->pipeline_status_id,
            'pipeline_status' => $this->pipelineStatus !== null
                ? ['id' => $this->pipelineStatus->id, 'name' => $this->pipelineStatus->name, 'color' => $this->pipelineStatus->color]
                : null,
            'country_id' => $this->country_id,
            'country' => $this->summarize($this->country, geo: true),
            'state_id' => $this->state_id,
            'state' => $this->summarize($this->state, geo: true),
            'province_id' => $this->province_id,
            'province' => $this->summarize($this->province, geo: true),
            'city_id' => $this->city_id,
            'city' => $this->summarize($this->city, geo: true),
            'geo_scope' => GeoScopeLevel::for($this->country_id, $this->state_id, $this->province_id, $this->city_id)?->value,
            'product_lines' => $this->summarizeProductLines($this->productLines),
            'partner_id' => $this->partner_id,
            'partner' => $this->summarize($this->partner),
            'operational_site_id' => $this->operational_site_id,
            'operational_site' => $this->summarizeOperationalSite($this->operationalSite),
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'total_budget' => $totalBudget,
            'target_lead' => $this->target_lead,
            // BR-7: computed budget visibility, never blocking the write (D-4).
            'allocated_budget' => $this->formatMoney($allocatedBudget),
            'remaining_budget' => $totalBudget === null ? null : $this->formatMoney((float) $totalBudget - $allocatedBudget),
            'campaigns_count' => (int) ($this->campaigns_count ?? 0),
            'created_at' => $this->created_at,
        ];
    }

    /**
     * A related row projected to {id, name}. `$geo` localizes the name to
     * Italian (country/state/province/city only) — never applied to the other
     * relations, whose names are user data (a company could be named "Milan").
     *
     * @return array{id: int, name: string}|null
     */
    private function summarize(?Model $related, bool $geo = false): ?array
    {
        if ($related === null) {
            return null;
        }

        $name = $geo ? GeoNameLocalizer::toItalian($related->name) : $related->name;

        return ['id' => $related->id, 'name' => $name];
    }

    private function formatMoney(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    /**
     * @return array<int, array{id: int, business_function: array{id: int, name: string}|null, product_category: array{id: int, name: string}|null}>
     */
    private function summarizeProductLines(iterable $lines): array
    {
        return collect($lines)
            ->map(fn (Model $line): array => [
                'id' => $line->id,
                'business_function' => $this->summarize($line->businessFunction),
                'product_category' => $this->summarize($line->productCategory),
            ])
            ->values()
            ->all();
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
