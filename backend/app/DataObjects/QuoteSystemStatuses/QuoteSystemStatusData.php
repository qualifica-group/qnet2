<?php

declare(strict_types=1);

namespace App\DataObjects\QuoteSystemStatuses;

/**
 * The validated write payload of a quote system status (spec 0404 part A),
 * shared by create (every key required) and update (only the submitted keys).
 */
final readonly class QuoteSystemStatusData
{
    /**
     * @param  array<string, mixed>  $attributes  only the submitted keys among name/color/behavior
     */
    private function __construct(private array $attributes) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(array_intersect_key($validated, array_flip(['name', 'color', 'behavior'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function submittedAttributes(): array
    {
        return $this->attributes;
    }

    public function behavior(): ?string
    {
        $behavior = $this->attributes['behavior'] ?? null;

        return is_string($behavior) ? $behavior : null;
    }
}
