<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\InvoiceTag;
use Illuminate\Http\Request;

/**
 * For-select projection of an InvoiceTag (GET /api/invoice-tags/for-select,
 * spec 0247): label = name, `meta` carries the badge `color` and `is_active`
 * (the reorder sheet asks for inactive rows and must tell them apart).
 *
 * @mixin InvoiceTag
 */
class InvoiceTagForSelectResource extends ForSelectResource
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
                'is_active' => $this->is_active,
            ],
        ];
    }
}
