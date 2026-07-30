<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;

/**
 * For-select projection of a PaymentMethod (GET
 * /api/payment-methods/for-select, spec 0068).
 *
 * `label` = name, `subtitle` = code (always present, `code` is required and
 * unique), `meta.payment_days` lets a consumer form pre-fill an expected
 * due date without a second lookup.
 *
 * @mixin PaymentMethod
 */
class PaymentMethodForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
            'subtitle' => $this->code,
            'meta' => ['payment_days' => $this->payment_days],
        ];
    }
}
