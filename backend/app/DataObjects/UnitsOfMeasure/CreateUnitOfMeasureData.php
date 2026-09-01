<?php

namespace App\DataObjects\UnitsOfMeasure;

/**
 * Validated payload for creating a unit of measure
 * (POST /api/units-of-measure, spec 0088). Declared DTO (no "magic flying
 * array") so the StoreUnitOfMeasureRequest -> UnitOfMeasureService contract
 * is explicit — see standards/architecture.md -> Data Transfer Objects.
 * `code` is REQUIRED here (create-only, D-1): immutable for the rest of the
 * record's life, enforced by UpdateUnitOfMeasureRequest, never exposed on
 * UpdateUnitOfMeasureData.
 */
final readonly class CreateUnitOfMeasureData
{
    public function __construct(
        public string $name,
        public string $code,
        public string $symbol,
        public ?string $description,
    ) {}

    /**
     * Build from the validated StoreUnitOfMeasureRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            code: (string) $data['code'],
            symbol: (string) $data['symbol'],
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
            'symbol' => $this->symbol,
            'description' => $this->description,
        ];
    }
}
