<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\ContractStatus;
use Illuminate\Http\Request;

/**
 * For-select projection of a ContractStatus (GET
 * /api/contract-statuses/for-select).
 *
 * Minimal by design (ADR 0011): label = name, no subtitle/avatar.
 * `meta.system_key` (spec 0072, D-2) lets the frontend recognize/pin the
 * system rows in an entity-backed select without a second lookup.
 *
 * @mixin ContractStatus
 */
class ContractStatusForSelectResource extends ForSelectResource
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
