<?php

namespace App\DataObjects\ProductTypologies;

use App\Enums\SupplierCommissionDirection;

/**
 * Validated payload for creating a product typology
 * (POST /api/product-typologies, spec 0099). Declared DTO (no "magic flying
 * array") so the StoreProductTypologyRequest -> ProductTypologyService
 * contract is explicit — see standards/architecture.md -> Data Transfer
 * Objects. `code` is REQUIRED here (create-only, D-2): immutable for the rest
 * of the record's life, enforced by UpdateProductTypologyRequest, never
 * exposed on UpdateProductTypologyData.
 */
final readonly class CreateProductTypologyData
{
    public function __construct(
        public string $name,
        public string $code,
        public ?string $description,
        public bool $supplierCommissionEnabled = false,
        public ?SupplierCommissionDirection $supplierCommissionDirection = null,
    ) {}

    /**
     * Build from the validated StoreProductTypologyRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        $enabled = (bool) ($data['supplier_commission_enabled'] ?? false);

        return new self(
            name: (string) $data['name'],
            code: (string) $data['code'],
            description: array_key_exists('description', $data) ? $data['description'] : null,
            supplierCommissionEnabled: $enabled,
            // Spec 0202 D-7: a disabled switch always stores a null direction.
            supplierCommissionDirection: $enabled && isset($data['supplier_commission_direction'])
                ? SupplierCommissionDirection::from($data['supplier_commission_direction'])
                : null,
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
            'supplier_commission_enabled' => $this->supplierCommissionEnabled,
            'supplier_commission_direction' => $this->supplierCommissionDirection,
        ];
    }
}
