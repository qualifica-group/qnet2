<?php

namespace App\DataObjects\WorkOrders;

/**
 * Validated payload of PATCH /api/work-orders/{workOrder}/contract-data/lines/{quoteLine}
 * (spec 0201). Every key is optional and nullable-meaningful (status and
 * agreement clear back to null), so `attributes()` carries ONLY the keys the
 * client sent: an absent key leaves the stored value untouched.
 */
final readonly class UpdateWorkOrderLinePaymentData
{
    /**
     * @param  array<string, mixed>  $attributes  keyed by `work_order_line_payments` column
     */
    private function __construct(private array $attributes) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        $attributes = [];

        if (array_key_exists('work_order_payment_status_id', $validated)) {
            $attributes['work_order_payment_status_id'] = $validated['work_order_payment_status_id'] === null ? null : (int) $validated['work_order_payment_status_id'];
        }

        if (array_key_exists('payment_agreement', $validated)) {
            $attributes['payment_agreement'] = $validated['payment_agreement'];
        }

        if (array_key_exists('has_unpaid', $validated)) {
            $attributes['has_unpaid'] = (bool) $validated['has_unpaid'];
        }

        return new self($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }
}
