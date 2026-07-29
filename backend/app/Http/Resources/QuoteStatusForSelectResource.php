<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\QuoteStatus;
use Illuminate\Http\Request;

/**
 * For-select projection of a QuoteStatus (GET
 * /api/quote-statuses/for-select).
 *
 * Minimal by design (ADR 0011): label = name, no subtitle/avatar.
 * `meta.system_key` (spec 0065, D-2) lets the frontend recognize/pin the
 * system rows in an entity-backed select without a second lookup.
 *
 * @mixin QuoteStatus
 */
class QuoteStatusForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
            'meta' => ['system_key' => $this->system_key],
        ];
    }
}
