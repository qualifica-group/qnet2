<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Payment method lookup entity (spec 0068): a consumer-agnostic anagraphic
 * (name/code/payment_method_code/description/payment_instructions/
 * payment_days/is_active) describing a payment modality selectable by the
 * modules that will consume it. `quotes` is the FIRST such consumer (user
 * directive 2026-07-30) and is what makes delete() a guarded operation.
 * `code` is the ONLY unique field (user directive 2026-08-05) and is
 * immutable after create (D-3, enforced in UpdatePaymentMethodRequest, not
 * here); `payment_method_code` is the fiscal/legacy classification code
 * (e.g. "MP01"), deliberately shared by several methods. `sort_order` stays
 * fillable — server-managed by
 * App\Services\PaymentMethods\PaymentMethodOrderManager, never accepted from
 * the client at the FormRequest layer.
 */
#[Fillable(['name', 'code', 'payment_method_code', 'description', 'payment_instructions', 'payment_days', 'sort_order', 'is_active'])]
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

    /**
     * The Quotes that agreed on this payment modality (user directive
     * 2026-07-30) — the referenced-by set PaymentMethodService::delete()
     * guards against.
     *
     * @return HasMany<Quote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }
}
