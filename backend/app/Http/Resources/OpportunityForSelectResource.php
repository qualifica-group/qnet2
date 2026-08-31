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
 * `managers` (spec 0087, D-5) carries the opportunity's own Gestori Account as
 * `{id, name, position}`, ordered by position and `[]` when it has none — the
 * Offerta form prefills its team from them on selection, the same way the
 * Opportunity form prefills from RegistryForSelectResource.meta.managers.
 * QuoteService applies the identical copy server-side when `manager_slots` is
 * absent from the payload, so the two paths cannot disagree.
 *
 * `supervisor` is a plain copy like the other two roles (user directive
 * 2026-08-31, superseding 2026-08-06): it is no longer filtered to the
 * opportunity's Gestori Account. QuoteService::applySnapshotDefaults applies
 * the same copy server-side when the key is absent from the payload.
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
                'supervisor' => $this->relationRef($this->supervisor),
                'operational_site' => OperationalSiteLabel::summarize($this->operationalSite),
                'managers' => $this->managers
                    ->map(static fn (User $manager): array => [
                        'id' => $manager->id,
                        'name' => $manager->name,
                        'position' => (int) $manager->pivot->position,
                    ])
                    ->all(),
            ],
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function relationRef(?Model $related): ?array
    {
        return $related !== null ? ['id' => $related->id, 'name' => $related->name] : null;
    }
}
