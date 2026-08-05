<?php

namespace App\DataObjects\PaymentMethods;

/**
 * Validated payload for a partial (PATCH) payment method update (PUT/PATCH
 * /api/payment-methods/{paymentMethod}, spec 0068).
 *
 * Declared DTO (no "magic flying array") so the UpdatePaymentMethodRequest ->
 * PaymentMethodService contract is explicit. `payment_method_code`/
 * `description`/`payment_instructions`/
 * `payment_days` are legitimately nullable VALUES (they clear back to none)
 * and `is_active` a legitimately optional boolean, so a plain null property
 * cannot distinguish "not submitted" from "submitted as null/false" — the
 * `*Submitted` flags carry that distinction explicitly, mirroring
 * `UpdateRewardStatusData`'s own pattern. `code`/`sort_order` are GONE — `code`
 * is immutable after create (D-3, rejected at the FormRequest layer via a
 * `prohibited` rule, never reaches this DTO), `sort_order` is server-managed
 * (see App\Services\PaymentMethods\PaymentMethodOrderManager).
 */
final readonly class UpdatePaymentMethodData
{
    public function __construct(
        public ?string $name = null,
        public ?string $paymentMethodCode = null,
        public bool $paymentMethodCodeSubmitted = false,
        public ?string $description = null,
        public bool $descriptionSubmitted = false,
        public ?string $paymentInstructions = null,
        public bool $paymentInstructionsSubmitted = false,
        public ?int $paymentDays = null,
        public bool $paymentDaysSubmitted = false,
        public ?bool $isActive = null,
        public bool $isActiveSubmitted = false,
    ) {}

    /**
     * Build from the validated UpdatePaymentMethodRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: array_key_exists('name', $data) ? (string) $data['name'] : null,
            paymentMethodCode: array_key_exists('payment_method_code', $data) ? $data['payment_method_code'] : null,
            paymentMethodCodeSubmitted: array_key_exists('payment_method_code', $data),
            description: array_key_exists('description', $data) ? $data['description'] : null,
            descriptionSubmitted: array_key_exists('description', $data),
            paymentInstructions: array_key_exists('payment_instructions', $data) ? $data['payment_instructions'] : null,
            paymentInstructionsSubmitted: array_key_exists('payment_instructions', $data),
            paymentDays: array_key_exists('payment_days', $data) && $data['payment_days'] !== null ? (int) $data['payment_days'] : null,
            paymentDaysSubmitted: array_key_exists('payment_days', $data),
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null,
            isActiveSubmitted: array_key_exists('is_active', $data),
        );
    }

    /**
     * Only the attributes the client actually submitted, ready for a partial
     * mass-assignment update (framework array boundary).
     *
     * @return array<string, mixed>
     */
    public function submittedAttributes(): array
    {
        $attributes = [];

        if ($this->name !== null) {
            $attributes['name'] = $this->name;
        }

        if ($this->paymentMethodCodeSubmitted) {
            $attributes['payment_method_code'] = $this->paymentMethodCode;
        }

        if ($this->descriptionSubmitted) {
            $attributes['description'] = $this->description;
        }

        if ($this->paymentInstructionsSubmitted) {
            $attributes['payment_instructions'] = $this->paymentInstructions;
        }

        if ($this->paymentDaysSubmitted) {
            $attributes['payment_days'] = $this->paymentDays;
        }

        if ($this->isActiveSubmitted) {
            $attributes['is_active'] = $this->isActive;
        }

        return $attributes;
    }
}
