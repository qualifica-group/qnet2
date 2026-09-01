<?php

namespace App\DataObjects\UnitsOfMeasure;

/**
 * Validated payload for a partial (PATCH) unit of measure update
 * (PUT/PATCH /api/units-of-measure/{unitOfMeasure}, spec 0088).
 *
 * Declared DTO (no "magic flying array") so the UpdateUnitOfMeasureRequest ->
 * UnitOfMeasureService contract is explicit. `description` is a legitimately
 * nullable VALUE (it clears back to none), so a plain null property cannot
 * distinguish "not submitted" from "submitted as null" — the `*Submitted`
 * flag carries that distinction, mirroring UpdatePaymentMethodData. `code`
 * is GONE — immutable after create (D-1, rejected at the FormRequest layer
 * via a `prohibited` rule, never reaches this DTO).
 */
final readonly class UpdateUnitOfMeasureData
{
    public function __construct(
        public ?string $name = null,
        public ?string $symbol = null,
        public ?string $description = null,
        public bool $descriptionSubmitted = false,
    ) {}

    /**
     * Build from the validated UpdateUnitOfMeasureRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: array_key_exists('name', $data) ? (string) $data['name'] : null,
            symbol: array_key_exists('symbol', $data) ? (string) $data['symbol'] : null,
            description: array_key_exists('description', $data) ? $data['description'] : null,
            descriptionSubmitted: array_key_exists('description', $data),
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

        if ($this->symbol !== null) {
            $attributes['symbol'] = $this->symbol;
        }

        if ($this->descriptionSubmitted) {
            $attributes['description'] = $this->description;
        }

        return $attributes;
    }
}
