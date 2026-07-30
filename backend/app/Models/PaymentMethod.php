<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Payment method lookup entity (spec 0068): a consumer-agnostic anagraphic
 * (name/code/description/payment_instructions/payment_days/is_active)
 * describing a payment modality selectable by the modules that will consume
 * it (quotes, offers, contracts, orders, invoices — none yet, D-2 out of
 * scope). `name`/`code` are unique; `code` is immutable after create (D-3,
 * enforced in UpdatePaymentMethodRequest, not here). `sort_order` stays
 * fillable — server-managed by
 * App\Services\PaymentMethods\PaymentMethodOrderManager, never accepted from
 * the client at the FormRequest layer.
 */
#[Fillable(['name', 'code', 'description', 'payment_instructions', 'payment_days', 'sort_order', 'is_active'])]
class PaymentMethod extends BaseModel
{
    /** @use HasFactory<PaymentMethodFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_days' => 'int',
            'sort_order' => 'int',
            'is_active' => 'bool',
        ];
    }
}
