<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\WorkOrderPaymentStatus;
use Illuminate\Http\Request;

/**
 * For-select projection of a WorkOrderPaymentStatus (spec 0201). On top of the
 * standard `{id, label}` (ADR 0011) it carries the flat presentation fields the
 * line-payment editor needs: `name`, `color` (the dot) and `allows_delivery`.
 *
 * @mixin WorkOrderPaymentStatus
 */
class WorkOrderPaymentStatusForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
            'name' => $this->name,
            'color' => $this->color,
            'allows_delivery' => $this->allows_delivery,
        ];
    }
}
