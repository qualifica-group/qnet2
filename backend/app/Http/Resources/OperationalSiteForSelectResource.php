<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\Address;
use App\Models\OperationalSite;
use App\Support\OperationalSiteLabel;
use Illuminate\Http\Request;

/**
 * For-select projection of an OperationalSite (GET /api/operational-sites/for-select).
 *
 * A site has no own name (identity = its address, see OperationalSite): the
 * label is composed by App\Support\OperationalSiteLabel, the ONE definition
 * of it (spec 0112 D-8) — `line1`, plus " - {city}" when the address has a
 * city; subtitle = postal_code when present (omitted otherwise,
 * ForSelectResource's null-optional rule). `meta` carries the site's Regione
 * ({state_id, state_label}, directive 2026-07-21) so the Lead form can
 * auto-fill it when a Sede is picked; omitted when the site has no region.
 * Relies on the Service eager-loading the primary address (+ city, + state) —
 * never a full OperationalSiteResource.
 *
 * @mixin OperationalSite
 */
class OperationalSiteForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        /** @var Address|null $address */
        $address = $this->addresses->first();

        return [
            'id' => $this->id,
            'label' => OperationalSiteLabel::compose($address),
            'subtitle' => $address?->postal_code,
            'meta' => $this->composeMeta($address),
        ];
    }

    /**
     * The Regione bag consumed by the Lead form's auto-fill: the primary
     * address' `state_id` plus its localized name (same label the `states`
     * for-select shows). Null — hence omitted by ForSelectResource — when the
     * site's primary address has no region.
     *
     * @return array{state_id: int, state_label: string|null}|null
     */
    private function composeMeta(?Address $address): ?array
    {
        if ($address?->state_id === null) {
            return null;
        }

        return [
            'state_id' => $address->state_id,
            'state_label' => $address->state?->localizedName(),
        ];
    }
}
