<?php

declare(strict_types=1);

namespace App\DataObjects\ContractStatuses;

/**
 * Validated payload for creating a contract status (POST
 * /api/contract-statuses, spec 0072). Declared DTO (no "magic flying array")
 * so the StoreContractStatusRequest -> ContractStatusService contract is
 * explicit — see standards/architecture.md -> Data Transfer Objects.
 *
 * `sort_order`/`system_key` are GONE from this DTO — server-managed, placed
 * by App\Services\Statuses\StatusOrderManager::placeNew() inside
 * ContractStatusService::create(), never accepted from the client.
 * `isDefault` carries the CLIENT'S request only — the effective, persisted
 * `is_default` is resolved by
 * App\Services\Contracts\ContractStatusDefaultManager (BR-5), never written
 * directly from this DTO. `color`/`group` are REQUIRED — every row, system
 * or custom, carries a color and a classification
 * (App\Enums\ContractStatusGroup).
 */
final readonly class CreateContractStatusData
{
    public function __construct(
        public string $name,
        public ?string $description,
        public string $color,
        public string $group,
        public bool $isActive,
        public bool $isDefault,
    ) {}

    /**
     * Build from the validated StoreContractStatusRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            description: array_key_exists('description', $data) ? $data['description'] : null,
            color: (string) $data['color'],
            group: (string) $data['group'],
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            isDefault: array_key_exists('is_default', $data) ? (bool) $data['is_default'] : false,
        );
    }

    /**
     * Attributes ready for mass-assignment, EXCLUDING `is_default` — the
     * caller (ContractStatusService::create()) merges in the effective value
     * resolved by ContractStatusDefaultManager before persisting.
     *
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
