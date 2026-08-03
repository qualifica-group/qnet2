<?php

namespace App\DataObjects\RewardStatuses;

use App\Enums\RewardStatusGroup;

/**
 * Validated payload for creating a reward status (POST /api/reward-statuses,
 * spec 0060). Declared DTO (no "magic flying array") so the
 * StoreRewardStatusRequest -> RewardStatusService contract is explicit — see
 * standards/architecture.md -> Data Transfer Objects.
 *
 * `sort_order`/`system_key` are GONE from this DTO — server-managed, placed
 * by App\Services\Statuses\StatusOrderManager::placeNew() inside
 * RewardStatusService::create(), never accepted from the client. `color` is
 * REQUIRED (D-4, BR-2), never nullable.
 */
final readonly class CreateRewardStatusData
{
    public function __construct(
        public string $name,
        public ?string $description,
        public string $color,
        public RewardStatusGroup $group,
        public bool $isActive,
    ) {}

    /**
     * Build from the validated StoreRewardStatusRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            description: array_key_exists('description', $data) ? $data['description'] : null,
            color: (string) $data['color'],
            group: RewardStatusGroup::from((string) $data['group']),
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'color' => $this->color,
            'group' => $this->group,
            'is_active' => $this->isActive,
        ];
    }
}
