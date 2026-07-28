<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A single "Gestione Richieste" category tab (spec 0064, M3): id/name plus
 * the DISTINCT-request count in the actor's scope, annotated onto the model
 * by RequestCategoryTabsResolver's aggregated query.
 *
 * @mixin ProductCategory
 */
class ProductCategoryTabResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'requests_count' => (int) $this->requests_count,
        ];
    }
}
