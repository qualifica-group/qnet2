<?php

namespace App\Http\Resources;

use App\Models\OperationalSite;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\OperationalSiteLabel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Projection of a StockMovement (spec 0307). Expects the relations loaded by
 * StockMovementService::MOVEMENT_RELATIONS.
 *
 * @mixin StockMovement
 */
class StockMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'product' => StockProductSummary::of($this->product),
            'quantity' => $this->quantity,
            'from_operational_site' => $this->siteSummary($this->fromOperationalSite),
            'from_user' => $this->userSummary($this->fromUser),
            'to_operational_site' => $this->siteSummary($this->toOperationalSite),
            'to_user' => $this->userSummary($this->toUser),
            'moved_on' => $this->moved_on->format('Y-m-d'),
            'note' => $this->note,
            'created_by' => $this->userSummary($this->creator),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, label: string}|null
     */
    private function siteSummary(?OperationalSite $site): ?array
    {
        return $site === null ? null : ['id' => $site->id, 'label' => OperationalSiteLabel::displayName($site)];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function userSummary(?User $user): ?array
    {
        return $user === null ? null : ['id' => $user->id, 'name' => $user->name];
    }
}
