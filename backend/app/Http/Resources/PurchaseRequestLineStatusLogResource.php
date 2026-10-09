<?php

namespace App\Http\Resources;

use App\Models\PurchaseRequestLineStatusLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the status history of a purchase request line (spec 0208, D-9).
 * Expects `user:id,name` loaded.
 *
 * @mixin PurchaseRequestLineStatusLog
 */
class PurchaseRequestLineStatusLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => ['id' => $this->user->id, 'name' => $this->user->name],
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'reason' => $this->reason,
            'is_bulk' => $this->bulk_group_id !== null,
            'created_at' => $this->created_at,
        ];
    }
}
