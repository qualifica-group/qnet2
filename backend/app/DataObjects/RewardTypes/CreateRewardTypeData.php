<?php

namespace App\DataObjects\RewardTypes;

/**
 * Validated payload for creating a reward type (POST /api/reward-types,
 * spec 0058). Declared DTO (no "magic flying array") so the
 * StoreRewardTypeRequest -> RewardTypeService contract is explicit — see
 * standards/architecture.md -> Data Transfer Objects.
 *
 * `color` is REQUIRED, never nullable (D-5) — the divergence from the
 * `opportunity-statuses` template, where color is optional.
 */
final readonly class CreateRewardTypeData
{
    public function __construct(
        public string $name,
        public string $color,
    ) {}

    /**
     * Build from the validated StoreRewardTypeRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            color: (string) $data['color'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'name' => $this->name,
            'color' => $this->color,
        ];
    }
}
