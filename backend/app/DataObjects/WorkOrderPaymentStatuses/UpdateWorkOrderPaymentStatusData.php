<?php

namespace App\DataObjects\WorkOrderPaymentStatuses;

/**
 * Validated payload for a partial update of a work order payment status
 * (spec 0201). `description` is a legitimately nullable value, so
 * `descriptionSubmitted` tells "not sent" from "cleared"; the booleans carry
 * the same distinction through their `*Submitted` flags.
 */
final readonly class UpdateWorkOrderPaymentStatusData
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public bool $descriptionSubmitted = false,
        public ?string $color = null,
        public ?bool $isActive = null,
        public bool $isActiveSubmitted = false,
        public ?bool $allowsDelivery = null,
        public bool $allowsDeliverySubmitted = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: array_key_exists('name', $data) ? (string) $data['name'] : null,
            description: array_key_exists('description', $data) ? $data['description'] : null,
            descriptionSubmitted: array_key_exists('description', $data),
            color: array_key_exists('color', $data) ? (string) $data['color'] : null,
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null,
            isActiveSubmitted: array_key_exists('is_active', $data),
            allowsDelivery: array_key_exists('allows_delivery', $data) ? (bool) $data['allows_delivery'] : null,
            allowsDeliverySubmitted: array_key_exists('allows_delivery', $data),
        );
    }

    /**
     * Only the attributes the client actually submitted.
     *
     * @return array<string, mixed>
     */
    public function submittedAttributes(): array
    {
        $attributes = [];

        if ($this->name !== null) {
            $attributes['name'] = $this->name;
        }

        if ($this->descriptionSubmitted) {
            $attributes['description'] = $this->description;
        }

        if ($this->color !== null) {
            $attributes['color'] = $this->color;
        }

        if ($this->isActiveSubmitted) {
            $attributes['is_active'] = $this->isActive;
        }

        if ($this->allowsDeliverySubmitted) {
            $attributes['allows_delivery'] = $this->allowsDelivery;
        }

        return $attributes;
    }
}
