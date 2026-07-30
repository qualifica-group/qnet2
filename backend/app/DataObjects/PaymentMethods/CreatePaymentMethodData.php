<?php

namespace App\DataObjects\PaymentMethods;

/**
 * Validated payload for creating a payment method (POST /api/payment-methods,
 * spec 0068). Declared DTO (no "magic flying array") so the
 * StorePaymentMethodRequest -> PaymentMethodService contract is explicit —
 * see standards/architecture.md -> Data Transfer Objects.
 *
 * `sort_order` is GONE from this DTO — server-managed, placed by
 * App\Services\PaymentMethods\PaymentMethodOrderManager::placeNew() inside
 * PaymentMethodService::create(), never accepted from the client. `code` is
 * REQUIRED here (create-only, D-3): immutable for the rest of the record's
 * life, enforced by UpdatePaymentMethodRequest, never exposed on
 * UpdatePaymentMethodData.
 */
final readonly class CreatePaymentMethodData
{
    public function __construct(
        public string $name,
        public string $code,
        public ?string $description,
        public ?string $paymentInstructions,
        public ?int $paymentDays,
        public bool $isActive,
    ) {}

    /**
     * Build from the validated StorePaymentMethodRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            code: (string) $data['code'],
            description: array_key_exists('description', $data) ? $data['description'] : null,
            paymentInstructions: array_key_exists('payment_instructions', $data) ? $data['payment_instructions'] : null,
            paymentDays: array_key_exists('payment_days', $data) && $data['payment_days'] !== null ? (int) $data['payment_days'] : null,
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'payment_instructions' => $this->paymentInstructions,
            'payment_days' => $this->paymentDays,
            'is_active' => $this->isActive,
        ];
    }
}
