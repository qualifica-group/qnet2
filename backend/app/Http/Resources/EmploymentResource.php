<?php

namespace App\Http\Resources;

use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
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
            'business_function_id' => $this->business_function_id,
            'company_id' => $this->company_id,
            'primary_operational_site_id' => $this->primary_operational_site_id,
            'remote_operational_site_ids' => $this->remote_operational_site_ids,

            'reports_to' => $this->when(
                $this->relationLoaded('reportsTo') && $this->reportsTo !== null,
                fn (): array => $this->reference($this->reportsTo, static fn (User $user): string => $user->name),
            ),
            'business_function' => $this->when(
                $this->relationLoaded('businessFunction') && $this->businessFunction !== null,
                fn (): array => $this->reference($this->businessFunction, static fn (BusinessFunction $function): string => $function->name),
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
