<?php

namespace App\Http\Resources;

use App\Authorization\LegacyIdVisibility;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentMethod
 */
class PaymentMethodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'old_id' => $this->when(LegacyIdVisibility::allows($request->user()), $this->old_id),
            'name' => $this->name,
            'code' => $this->code,
            'payment_method_code' => $this->payment_method_code,
            'payment_condition' => $this->payment_condition->value,
            'description' => $this->description,
            'payment_instructions' => $this->payment_instructions,
            'payment_days' => $this->payment_days,
            'installments_count' => $this->installments_count,
            'days_between_installments' => $this->days_between_installments,
            'end_of_month' => $this->end_of_month,
            'end_of_month_extra_days' => $this->end_of_month_extra_days,
            'excluded_month_1' => $this->excluded_month_1,
            'excluded_month_2' => $this->excluded_month_2,
            'vat_allocation' => $this->vat_allocation->value,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
