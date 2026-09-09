<?php

namespace App\Http\Resources;

use App\Models\Company;
use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EmploymentProfile
 *
 * The nested `employment` object (spec 0015): scalar fields, the relation
 * ids, and each relation's {id,label} reference — emitted only when that
 * relation was eager-loaded (mirrors PersonalDataResource's `whenLoaded`
 * discipline, avoiding an N+1 per row).
 *
 * The site membership (spec 0103) is no longer a single `operational_site_id`
 * column: `primary_operational_site_id`/`remote_operational_site_ids` proxy
 * the profile's own accessors (which read off the `operationalSites` pivot
 * collection), and the two reference shapes below are derived from that SAME
 * collection — so eager-loading `operationalSites.addresses.city` covers
 * every one of the four keys with a single relation.
 *
 * The assignment competence (spec 0111) follows the same discipline:
 * `product_lines` is emitted only when `productLines` was eager-loaded. It
 * REPLACES the former `business_function_id`/`business_function` pair (D-1
 * dropped the column) and the category-only keys of spec 0110, and carries
 * the same shape every other owner of the collection emits (see
 * OpportunityResource::summarizeProductLines()).
 */
class EmploymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'is_manager' => $this->is_manager,
            'job_description' => $this->job_description,
            'relationship_type' => $this->relationship_type,
            'qualification_type' => $this->qualification_type,
            'hired_at' => $this->hired_at,
            'terminated_at' => $this->terminated_at,
            'standard_daily_minutes' => $this->standard_daily_minutes,
            'break_daily_minutes' => $this->break_daily_minutes,

            'reports_to_id' => $this->reports_to_id,
            'company_id' => $this->company_id,
            'primary_operational_site_id' => $this->primary_operational_site_id,
            'remote_operational_site_ids' => $this->remote_operational_site_ids,
            'product_lines' => $this->when(
                $this->relationLoaded('productLines'),
                fn (): array => $this->summarizeProductLines($this->productLines),
            ),

            'reports_to' => $this->when(
                $this->relationLoaded('reportsTo') && $this->reportsTo !== null,
                fn (): array => $this->reference($this->reportsTo, static fn (User $user): string => $user->name),
            ),
            'company' => $this->when(
                $this->relationLoaded('company') && $this->company !== null,
                fn (): array => $this->reference($this->company, static fn (Company $company): string => $company->denomination, static fn (Company $company): ?string => $company->vat_number),
            ),
            'primary_operational_site' => $this->when(
                $this->relationLoaded('operationalSites') && $this->primarySite() !== null,
                fn (): array => $this->reference($this->primarySite(), $this->operationalSiteLabel(...), $this->operationalSiteSubtitle(...)),
            ),
            'remote_operational_sites' => $this->when(
                $this->relationLoaded('operationalSites'),
                fn (): array => $this->remoteSites()
                    ->map(fn (OperationalSite $site): array => $this->reference($site, $this->operationalSiteLabel(...), $this->operationalSiteSubtitle(...)))
                    ->values()
                    ->all(),
            ),
        ];
    }

    /**
     * The competence rows in the shape shared by every owner of the
     * collection (spec 0111): `{id, business_function, product_category}`,
     * each reference `{id, name}` — deliberately NOT the `{id, label}`
     * reference() shape used by this resource's other relations, so the
     * frontend reads a user's rows exactly as it reads an opportunity's.
     *
     * @param  iterable<int, Model>  $lines
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
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function summarizeByName(?Model $related): ?array
    {
        return $related === null ? null : ['id' => $related->id, 'name' => $related->name];
    }

    /**
     * The at-most-one PHYSICAL site out of the eager-loaded `operationalSites`
     * collection (D-3) — reads the already-loaded relation, same no-N+1
     * reasoning as EmploymentProfile::primaryOperationalSiteId().
     */
    private function primarySite(): ?OperationalSite
    {
        return $this->operationalSites->first(fn (OperationalSite $site): bool => (bool) $site->pivot->is_primary);
    }

    /**
     * The zero-or-more REMOTE sites out of the same eager-loaded collection.
     *
     * @return Collection<int, OperationalSite>
     */
    private function remoteSites(): Collection
    {
        return $this->operationalSites->reject(fn (OperationalSite $site): bool => (bool) $site->pivot->is_primary);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  TModel  $model
     * @param  callable(TModel): string  $label
     * @param  (callable(TModel): ?string)|null  $subtitle
     * @return array{id: int, label: string, subtitle?: string|null}
     */
    private function reference(mixed $model, callable $label, ?callable $subtitle = null): array
    {
        $reference = ['id' => $model->id, 'label' => $label($model)];

        if ($subtitle !== null) {
            $reference['subtitle'] = $subtitle($model);
        }

        return $reference;
    }

    /**
     * "line1 - city" when a city is known, else just "line1" (spec 0015,
     * mirroring the operational-sites for-select label).
     */
    private function operationalSiteLabel(OperationalSite $site): string
    {
        $address = $site->primaryAddress;
        $city = $address?->city?->localizedName();

        return $city !== null ? "{$address->line1} - {$city}" : (string) $address?->line1;
    }

    private function operationalSiteSubtitle(OperationalSite $site): ?string
    {
        return $site->primaryAddress?->postal_code;
    }
}
