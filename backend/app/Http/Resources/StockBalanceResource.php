<?php

namespace App\Http\Resources;

use App\Models\StockBalance;
use App\Support\OperationalSiteLabel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Projection of a StockBalance (spec 0307). Expects the relations loaded by
 * StockMovementService::BALANCE_RELATIONS.
 *
 * @mixin StockBalance
 */
class StockBalanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product' => StockProductSummary::of($this->product),
            'operational_site' => [
                'id' => $this->operationalSite->id,
                'label' => OperationalSiteLabel::displayName($this->operationalSite),
                'is_active' => (bool) $this->operationalSite->is_active,
            ],
            'user' => $this->user === null ? null : [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'is_active' => (bool) $this->user->is_active,
            ],
            'quantity' => $this->quantity,
            'last_moved_on' => $this->last_moved_on->format('Y-m-d'),
        ];
    }
}
