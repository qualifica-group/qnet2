<?php

namespace App\DataObjects\ProductTypologies;

use App\Enums\SupplierCommissionDirection;

/**
 * Validated payload for a partial (PATCH) product typology update
 * (PUT/PATCH /api/product-typologies/{productTypology}, spec 0099).
 *
 * Declared DTO (no "magic flying array") so the UpdateProductTypologyRequest
 * -> ProductTypologyService contract is explicit. `description` is a
 * legitimately nullable VALUE (it clears back to none), so a plain null
 * property cannot distinguish "not submitted" from "submitted as null" — the
 * `*Submitted` flag carries that distinction, mirroring
 * UpdateUnitOfMeasureData. `code` is GONE — immutable after create (D-2,
 * rejected at the FormRequest layer via a `prohibited` rule, never reaches
 * this DTO).
 */
final readonly class UpdateProductTypologyData
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public bool $descriptionSubmitted = false,
        public ?string $color = null,
        public ?bool $supplierCommissionEnabled = null,
        public ?SupplierCommissionDirection $supplierCommissionDirection = null,
        public bool $supplierCommissionDirectionSubmitted = false,
    ) {}

    /**
     * Build from the validated UpdateProductTypologyRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: array_key_exists('name', $data) ? (string) $data['name'] : null,
            description: array_key_exists('description', $data) ? $data['description'] : null,
            descriptionSubmitted: array_key_exists('description', $data),
            color: array_key_exists('color', $data) ? (string) $data['color'] : null,
            supplierCommissionEnabled: array_key_exists('supplier_commission_enabled', $data) ? (bool) $data['supplier_commission_enabled'] : null,
            supplierCommissionDirection: isset($data['supplier_commission_direction'])
                ? SupplierCommissionDirection::from($data['supplier_commission_direction'])
                : null,
            supplierCommissionDirectionSubmitted: array_key_exists('supplier_commission_direction', $data),
        );
    }

    /**
     * Only the attributes the client actually submitted, ready for a partial
     * mass-assignment update.
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

        if ($this->supplierCommissionEnabled !== null) {
            $attributes['supplier_commission_enabled'] = $this->supplierCommissionEnabled;
        }

        if ($this->supplierCommissionDirectionSubmitted) {
            $attributes['supplier_commission_direction'] = $this->supplierCommissionDirection;
        }

        return $attributes;
    }
}
