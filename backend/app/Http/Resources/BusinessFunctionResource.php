<?php

namespace App\Http\Resources;

use App\Authorization\LegacyIdVisibility;
use App\Models\Address;
use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\OperationalSite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BusinessFunction
 */
class BusinessFunctionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'old_id' => $this->when(LegacyIdVisibility::allows($request->user()), $this->old_id),
            'name' => $this->name,
            'is_business_unit' => $this->is_business_unit,
            'is_business_service' => $this->is_business_service,
            'type' => $this->type(),
            'manager_id' => $this->manager_id,
            'manager' => $this->userSummary($this->manager),
            'parent_id' => $this->parent_id,
            'parent' => $this->parentSummary($this->parent),
            'user_ids' => $this->users->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all(),
            'users' => $this->users->map(fn (User $user): array => $this->userSummary($user))->all(),
            'operational_site_ids' => $this->operationalSites->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all(),
            'operational_sites' => $this->operationalSites->map(fn (OperationalSite $site): array => $this->operationalSiteSummary($site))->all(),
            'company_ids' => $this->companies->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all(),
            'companies' => $this->companies->map(static fn (Company $company): array => ['id' => $company->id, 'label' => (string) $company->denomination])->all(),
            'created_at' => $this->created_at,
        ];
    }

    /**
     * The client-facing mutually-exclusive selector, derived from the two
     * boolean columns (spec 0010) — the inverse of BusinessFunctionService's
     * type-to-booleans mapping.
     */
    private function type(): ?string
    {
        return match (true) {
            $this->is_business_unit => 'business_unit',
            $this->is_business_service => 'business_service',
            default => null,
        };
    }

    /**
     * @return array{id: int, name: string, avatar_url: string|null}|null
     */
    private function userSummary(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar_url' => $user->avatarDataUri(),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function parentSummary(?BusinessFunction $parent): ?array
    {
        if ($parent === null) {
            return null;
        }

        return ['id' => $parent->id, 'name' => $parent->name];
    }

    /**
     * @return array{id: int, label: string}
     */
    private function operationalSiteSummary(OperationalSite $site): array
    {
        /** @var Address|null $address */
        $address = $site->addresses->first();

        return ['id' => $site->id, 'label' => $this->composeAddressLabel($address)];
    }

    /**
     * Same composition as OperationalSiteForSelectResource/LeadOperationalSiteColumn:
     * "{line1} - {city}", or just line1 when the address has no city.
     */
    private function composeAddressLabel(?Address $address): string
    {
        if ($address === null) {
            return '';
        }

        $city = $address->city?->localizedName();

        return $city === null ? (string) $address->line1 : "{$address->line1} - {$city}";
    }
}
