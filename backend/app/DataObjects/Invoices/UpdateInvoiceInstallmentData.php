<?php

namespace App\DataObjects\Invoices;

/**
 * Validated payload of PATCH /api/invoice-installments/{installment} (spec 0197).
 * `payment_method_code` may be cleared with null, so the submitted flag tells
 * "left alone" from "set to none".
 */
final readonly class UpdateInvoiceInstallmentData
{
    public function __construct(
        public ?string $dueDate = null,
        public ?string $paymentMethodCode = null,
        public bool $paymentMethodCodeSubmitted = false,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            dueDate: $validated['due_date'] ?? null,
            paymentMethodCode: $validated['payment_method_code'] ?? null,
            paymentMethodCodeSubmitted: array_key_exists('payment_method_code', $validated),
        );
    }

    /**
     * The model attributes to write: only what was submitted.
     *
     * @return array<string, string|null>
     */
    public function attributes(): array
    {
        $attributes = [];

        if ($this->dueDate !== null) {
            $attributes['due_date'] = $this->dueDate;
        }

        if ($this->paymentMethodCodeSubmitted) {
            $attributes['payment_method_code'] = $this->paymentMethodCode;
        }

        return $attributes;
    }
}
