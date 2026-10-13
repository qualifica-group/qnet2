<?php

declare(strict_types=1);

namespace App\DataObjects\PurchaseRequestLineStatuses;

/**
 * Validated payload of a partial update of a line status label (spec 0248).
 * `icon` is a legitimately nullable value, so `iconSubmitted` tells "not sent"
 * from "sent as null"; `name`/`color` are `sometimes|required`, a non-null
 * value always means "sent".
 */
final readonly class UpdatePurchaseRequestLineStatusData
{
    public function __construct(
        public ?string $name = null,
        public ?string $color = null,
        public ?string $icon = null,
        public bool $iconSubmitted = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: array_key_exists('name', $data) ? (string) $data['name'] : null,
            color: array_key_exists('color', $data) ? (string) $data['color'] : null,
            icon: array_key_exists('icon', $data) ? $data['icon'] : null,
            iconSubmitted: array_key_exists('icon', $data),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function submittedAttributes(): array
    {
        $attributes = [];

        if ($this->name !== null) {
            $attributes['name'] = $this->name;
        }

        if ($this->color !== null) {
            $attributes['color'] = $this->color;
        }

        if ($this->iconSubmitted) {
            $attributes['icon'] = $this->icon;
        }

        return $attributes;
    }
}
