<?php

namespace App\DataObjects\RewardTypes;

/**
 * Validated payload for a partial (PATCH) reward type update (PUT/PATCH
 * /api/reward-types/{rewardType}, spec 0058).
 *
 * Declared DTO (no "magic flying array") so the UpdateRewardTypeRequest ->
 * RewardTypeService contract is explicit. Unlike
 * `UpdateOpportunityStatusData`, `color` carries NO `colorSubmitted` flag
 * (D-5a): it can never be resubmitted as null/empty (BR-2, `sometimes|required`
 * at the FormRequest layer), so a plain nullable property already
 * distinguishes "not submitted" (null) from "submitted" (non-empty string).
 */
final readonly class UpdateRewardTypeData
{
    public function __construct(
        public ?string $name = null,
        public ?string $color = null,
    ) {}

    /**
     * Build from the validated UpdateRewardTypeRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: array_key_exists('name', $data) ? (string) $data['name'] : null,
            color: array_key_exists('color', $data) ? (string) $data['color'] : null,
        );
    }

    /**
     * Only the attributes the client actually submitted, ready for a partial
     * mass-assignment update (framework array boundary).
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

        return $attributes;
    }
}
