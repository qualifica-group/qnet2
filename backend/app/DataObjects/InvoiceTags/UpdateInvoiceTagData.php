<?php

declare(strict_types=1);

namespace App\DataObjects\InvoiceTags;

/**
 * Validated payload for a partial invoice tag update (spec 0247). `description`
 * and `is_active` are legitimately null/false values, so the `*Submitted` flags
 * tell "not submitted" from "submitted as null/false"; `name` and `color` are
 * `sometimes|required`, so a non-null value always means "submitted".
 */
final readonly class UpdateInvoiceTagData
{
    public function __construct(
        public ?string $name = null,
        public ?string $color = null,
        public ?string $description = null,
        public bool $descriptionSubmitted = false,
        public ?bool $isActive = null,
        public bool $isActiveSubmitted = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: array_key_exists('name', $data) ? (string) $data['name'] : null,
            color: array_key_exists('color', $data) ? (string) $data['color'] : null,
            description: array_key_exists('description', $data) ? $data['description'] : null,
            descriptionSubmitted: array_key_exists('description', $data),
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null,
            isActiveSubmitted: array_key_exists('is_active', $data),
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

        if ($this->color !== null) {
            $attributes['color'] = $this->color;
        }

        if ($this->descriptionSubmitted) {
            $attributes['description'] = $this->description;
        }

        if ($this->isActiveSubmitted) {
            $attributes['is_active'] = $this->isActive;
        }

        return $attributes;
    }
}
