<?php

declare(strict_types=1);

namespace App\DataObjects\TaskTypes;

/**
 * Validated payload for creating a task type (POST /api/task-types, spec 0101,
 * D-4). Declared DTO (no "magic flying array") so the StoreTaskTypeRequest ->
 * TaskTypeService contract is explicit — see standards/architecture.md ->
 * Data Transfer Objects.
 *
 * `sort_order` is GONE from this DTO: server-managed, placed by
 * TaskTypeService::create(), never accepted from the client (AC-045).
 * `color` is REQUIRED and is a palette TOKEN of App\Support\BadgeTokens,
 * never a hex value; `icon` is nullable and comes from the same curated
 * server-side allow-list (D-4).
 */
final readonly class CreateTaskTypeData
{
    public function __construct(
        public string $name,
        public ?string $description,
        public string $color,
        public ?string $icon,
        public bool $isActive,
    ) {}

    /**
     * Build from the validated StoreTaskTypeRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            description: array_key_exists('description', $data) ? $data['description'] : null,
            color: (string) $data['color'],
            icon: array_key_exists('icon', $data) ? $data['icon'] : null,
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        );
    }

    /**
     * Attributes ready for mass-assignment. `sort_order` is merged in by
     * TaskTypeService::create(), never carried here.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'color' => $this->color,
            'icon' => $this->icon,
            'is_active' => $this->isActive,
        ];
    }
}
