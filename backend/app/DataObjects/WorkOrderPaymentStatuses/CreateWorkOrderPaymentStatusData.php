<?php

namespace App\DataObjects\WorkOrderPaymentStatuses;

/**
 * Validated payload for POST /api/work-order-payment-statuses (spec 0201).
 * `sort_order` and `old_id` are never accepted from the client: the first is
 * placed by WorkOrderPaymentStatusOrderManager, the second is written only by
 * the reference seeder.
 */
final readonly class CreateWorkOrderPaymentStatusData
{
    public function __construct(
        public string $name,
        public ?string $description,
        public string $color,
        public bool $isActive,
        public bool $allowsDelivery,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            description: array_key_exists('description', $data) ? $data['description'] : null,
            color: (string) $data['color'],
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            allowsDelivery: array_key_exists('allows_delivery', $data) ? (bool) $data['allows_delivery'] : false,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'color' => $this->color,
            'is_active' => $this->isActive,
            'allows_delivery' => $this->allowsDelivery,
        ];
    }
}
