<?php

declare(strict_types=1);

namespace App\DataObjects\TaskStatuses;

/**
 * Validated payload for creating a task status (POST /api/task-statuses, spec 0101,
 * D-4). Declared DTO (no "magic flying array") so the StoreTaskStatusRequest ->
 * TaskStatusService contract is explicit — see standards/architecture.md ->
 * Data Transfer Objects.
 *
 * `sort_order`/`system_key` is GONE from this DTO: server-managed, placed by
 * TaskStatusService::create(), never accepted from the client (AC-045).
 * `color` is REQUIRED and is a palette TOKEN of App\Support\BadgeTokens,
 * never a hex value; `icon` is nullable and comes from the same curated
 * server-side allow-list (D-4).
 * `completionPercentage` is REQUIRED (D-6): the Task's completion is a
 * PROJECTION of its status, so every status must declare one.
 * `group` is REQUIRED too (App\Enums\TaskStatusGroup): every row declares
 * the phase it belongs to — it is what decides whether reaching the status
 * closes the Task (D-7).
 */
final readonly class CreateTaskStatusData
{
    public function __construct(
        public string $name,
        public ?string $description,
        public string $color,
        public ?string $icon,
        public bool $isActive,
        public int $completionPercentage,
        public string $group,
    ) {}

    /**
     * Build from the validated StoreTaskStatusRequest payload.
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
            completionPercentage: (int) $data['completion_percentage'],
            group: (string) $data['group'],
        );
    }

    /**
     * Attributes ready for mass-assignment. `sort_order` is merged in by
     * TaskStatusService::create(), never carried here.
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
            'completion_percentage' => $this->completionPercentage,
            'group' => $this->group,
        ];
    }
}
