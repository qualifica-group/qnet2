<?php

namespace App\Http\Resources;

use App\Enums\GeoScopeLevel;
use App\Models\Address;
use App\Models\Campaign;
use App\Models\Project;
use App\Support\Geo\GeoNameLocalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Campaign
 *
 * BR-2: when the campaign is linked to a project (`project_id` set), its own
 * 3 classification columns are NULL in DB — this Resource always exposes the
 * EFFECTIVE values (read through the project when linked), plus the
 * `derived_from_project` flag the frontend uses to render them read-only.
 *
 * Geo (spec 0027, BR-5) is a PER-LEVEL merge, not an all-or-nothing switch:
 * `country`/`state`/`province`/`city` are each the linked project's value
 * when IT has one, else the campaign's OWN — `geo_locked_levels` tells the
 * frontend which of the four came from the project. Project-first (not
 * campaign-first) mirrors the precedence already used above for the 3 BR-2
 * classification fields: the write path (CampaignService + ProjectService's
 * BR-5 realignment cascade, spec 0027 addendum) already guarantees the
 * campaign's own column is NULL wherever the project owns the level, but
 * resolving the merge this way too closes the read-side blast radius should
 * that invariant ever be violated (stale row, future regression).
 *
 * Relies on CampaignService::loadDetail() having eager-loaded both branches
 * (own classification/geo + project's), so resolving either side here never
 * N+1s.
 *
 * Spec 0094, D-1/D-2: `business_function_id`/`business_function`/
 * `product_category_id`/`product_category` are REPLACED by `product_lines`
 * (one row per funzione-aziendale + categoria-prodotto pair, mirroring
 * OpportunityResource/ProjectResource) — the EFFECTIVE rows, same
 * project-first precedence as every other BR-2 field above: the linked
 * project's collection when derived, else the campaign's own.
 */
class CampaignResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $project = $this->project;
        $derivedFromProject = $project !== null;

        $pipelineStatus = $derivedFromProject ? $project->pipelineStatus : $this->pipelineStatus;
        $productLines = $derivedFromProject ? $project->productLines : $this->productLines;

        $country = $project?->country ?? $this->country;
        $state = $project?->state ?? $this->state;
        $province = $project?->province ?? $this->province;
        $city = $project?->city ?? $this->city;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'project_id' => $this->project_id,
            'project' => $project === null ? null : [
                'id' => $project->id,
                'code' => $project->code,
                'name' => $project->name,
            ],
            'name' => $this->name,
            'description' => $this->description,
            'partner_id' => $this->partner_id,
            'partner' => $this->summarize($this->partner),
            'operational_site_id' => $this->operational_site_id,
            'operational_site' => $this->summarizeOperationalSite($this->operationalSite),
            'derived_from_project' => $derivedFromProject,
            'pipeline_status_id' => $pipelineStatus?->id,
            'pipeline_status' => $pipelineStatus === null ? null : [
                'id' => $pipelineStatus->id,
                'name' => $pipelineStatus->name,
                'color' => $pipelineStatus->color,
            ],
            'country_id' => $country?->id,
            'country' => $this->summarize($country, geo: true),
            'state_id' => $state?->id,
            'state' => $this->summarize($state, geo: true),
            'province_id' => $province?->id,
            'province' => $this->summarize($province, geo: true),
            'city_id' => $city?->id,
            'city' => $this->summarize($city, geo: true),
            'geo_scope' => GeoScopeLevel::for($country?->id, $state?->id, $province?->id, $city?->id)?->value,
            'geo_locked_levels' => $this->geoLockedLevels($project),
            'product_lines' => $this->summarizeProductLines($productLines),
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'total_budget' => $this->total_budget,
            'target_lead' => $this->target_lead,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * The geo levels OWNED by the linked project (BR-5) — the levels the
     * frontend must render locked/read-only on the campaign form.
     *
     * @return array<int, string>
     */
    private function geoLockedLevels(?Project $project): array
    {
        if ($project === null) {
            return [];
        }

        return array_values(array_filter([
            $project->country_id !== null ? GeoScopeLevel::Country->value : null,
            $project->state_id !== null ? GeoScopeLevel::State->value : null,
            $project->province_id !== null ? GeoScopeLevel::Province->value : null,
            $project->city_id !== null ? GeoScopeLevel::City->value : null,
        ]));
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

    /**
     * The campaign's OWN sede (never the project's — independently editable
     * and stored, spec: prefill-modifiable, not read-through). No own name
     * column: label composed the same way LeadResource/
     * OperationalSiteForSelectResource do.
     *
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
