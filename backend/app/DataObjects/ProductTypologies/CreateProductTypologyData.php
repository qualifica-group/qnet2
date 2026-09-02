<?php

namespace App\DataObjects\ProductTypologies;

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
    ) {}

    /**
     * Build from the validated StoreProductTypologyRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            code: (string) $data['code'],
            description: array_key_exists('description', $data) ? $data['description'] : null,
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
        ];
    }
}
