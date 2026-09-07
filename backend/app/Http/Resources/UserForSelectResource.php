<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\Address;
use App\Models\OperationalSite;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * For-select projection of a User (GET /api/users/for-select).
 *
 * Minimal by design (ADR 0011): label = name, subtitle = email, optional
 * avatar_url as an inlined data URI when the user has an avatar. Relies on the
 * service projecting only the fields needed by the select plus the eager-loaded
 * avatar relation — never a full UserResource.
 *
 * `meta` (spec 0048, kept singular by spec 0103 AC-011) carries the operator's
 * PHYSICAL Sede — {operational_site_id, operational_site_label} — so the Lead
 * form can auto-fill the Sede when an Operatore is picked first. The list
 * itself may include the operator because of a REMOTE membership (D-1), but
 * `meta` always names the physical one, read off the pivot's `is_primary`
 * flag. Label composed "{line1} - {city}" from the site's primary address,
 * the SAME composition OperationalSiteForSelectResource/
 * LeadOperationalSiteColumn use (no shared helper — replicated for minimal
 * blast radius). Omitted when the user has no employment profile or no
 * physical Sede. Relies on the service eager-loading
 * `employment.operationalSites.addresses.city`.
 *
 * @mixin User
 */
class UserForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
            'subtitle' => $this->email,
            'avatar_url' => $this->avatarDataUri(),
            'meta' => $this->composeMeta(),
        ];
    }

    /**
     * @return array{operational_site_id: int, operational_site_label: string}|null
     */
    private function composeMeta(): ?array
    {
        $site = $this->employment?->operationalSites
            ->first(fn (OperationalSite $site): bool => (bool) $site->pivot->is_primary);

        if ($site === null) {
            return null;
        }

        /** @var Address|null $address */
        $address = $site->addresses->first();

        return [
            'operational_site_id' => $site->id,
            'operational_site_label' => $this->composeSiteLabel($address),
        ];
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
