<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\QuoteSystemStatus;
use Illuminate\Http\Request;

/**
 * For-select projection of a quote system status (spec 0404 part A): `meta`
 * carries the color dot, the behavior and the system key the workflow
 * configurator needs.
 *
 * @mixin QuoteSystemStatus
 */
class QuoteSystemStatusForSelectResource extends ForSelectResource
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
                'color' => $this->color,
                'behavior' => $this->behavior->value,
                'system_key' => $this->system_key,
            ],
        ];
    }
}
