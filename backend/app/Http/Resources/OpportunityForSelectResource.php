<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\Opportunity;
use Illuminate\Http\Request;

/**
 * For-select projection of an Opportunity (GET /api/opportunities/for-select,
 * ADR 0011): label = `name` (the derived `OPP_{id}` display name, spec 0057
 * D-5 — the only opportunity-owned descriptive column). Feeds the
 * `rewarded-referents` "opportunity" advanced filter (spec 0059).
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
        ];
    }
}
