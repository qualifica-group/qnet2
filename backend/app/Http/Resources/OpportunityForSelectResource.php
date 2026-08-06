<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\Opportunity;
use App\Models\User;
use App\Support\OperationalSiteLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * For-select projection of an Opportunity (GET /api/opportunities/for-select,
 * ADR 0011): label = `name` (the derived `OPP_{id}` display name, spec 0057
 * D-5 — the only opportunity-owned descriptive column). Feeds the
 * `rewarded-referents` "opportunity" advanced filter (spec 0059).
 *
 * `meta` carries what a new Quote snapshots from its opportunity: the three
 * commercial roles (spec 0065 D-3, directive 2026-07-29) plus the sede
 * operativa (directive 2026-07-30) — always present, each key null when the
 * opportunity has none. Mirrors RegistryForSelectResource. The site is a
 * `{id, label}` ref (OperationalSiteLabel), not `{id, name}`: it has no name
 * column of its own.
 *
 * `supervisor` is the ONE key that is not a plain copy: on an offer the
 * Supervisore can only be a Gestore Account of the opportunity (user
 * directive 2026-08-06), so an opportunity whose own Supervisore is not
 * among its GA prefills nothing — the same rule
 * QuoteService::applySnapshotDefaults applies server-side when the key is
 * absent from the payload.
 *
 * @mixin Opportunity
 */
class OpportunityForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
            'meta' => [
                'commercial' => $this->relationRef($this->commercial),
                'reporter' => $this->relationRef($this->reporter),
                'supervisor' => $this->relationRef($this->supervisorAsManager()),
                'operational_site' => OperationalSiteLabel::summarize($this->operationalSite),
            ],
        ];
    }

    /**
     * The opportunity's Supervisore only when they also hold a Gestore
     * Account slot on it; null otherwise. Reads the eager-loaded `managers`
     * (OpportunityService::forSelectBaseQuery), never a lazy relation.
     */
    private function supervisorAsManager(): ?User
    {
        $supervisor = $this->supervisor;

        if ($supervisor === null) {
            return null;
        }

        return $this->managers->contains('id', $supervisor->id) ? $supervisor : null;
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function relationRef(?Model $related): ?array
    {
        return $related !== null ? ['id' => $related->id, 'name' => $related->name] : null;
    }
}
