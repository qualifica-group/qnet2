<?php

namespace App\DataObjects\CostTypes;

/**
 * Validated payload for a partial cost type update (PUT/PATCH
 * /api/cost-types/{costType}, spec 0210). Only the submitted keys are applied:
 * `description` is nullable, so "not submitted" is told apart from "cleared"
 * by key presence.
 */
final readonly class UpdateCostTypeData
{
    /**
     * @param  array<string, mixed>  $submitted  validated attributes, already snake_case
     */
    private function __construct(private array $submitted) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(array_intersect_key($data, array_flip(['name', 'description', 'sort_order', 'is_active'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function submittedAttributes(): array
    {
        return $this->submitted;
    }
}
