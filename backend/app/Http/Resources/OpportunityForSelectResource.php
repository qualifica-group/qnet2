<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\Opportunity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * For-select projection of an Opportunity (GET /api/opportunities/for-select,
 * ADR 0011): label = `name` (the derived `OPP_{id}` display name, spec 0057
 * D-5 — the only opportunity-owned descriptive column). Feeds the
 * `rewarded-referents` "opportunity" advanced filter (spec 0059).
 *
 * `meta` carries the three commercial roles a new Quote snapshots from its
 * opportunity (spec 0065 D-3, directive 2026-07-29) — always present, each
 * key null when the opportunity has none. Mirrors RegistryForSelectResource.
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
