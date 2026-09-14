<?php

declare(strict_types=1);

namespace App\DataObjects\TaskTemplates;

/**
 * Validated payload for creating a task template (POST /api/task-templates,
 * spec 0124). Declared DTO (no "magic flying array") so the
 * StoreTaskTemplateRequest -> TaskTemplateService contract is explicit — see
 * standards/architecture.md -> Data Transfer Objects.
 */
final readonly class CreateTaskTemplateData
{
    /**
     * @param  array<int, TaskTemplateItemData>  $items
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public bool $isActive,
        public array $items,
    ) {}

    /**
     * Build from the validated StoreTaskTemplateRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        /** @var array<int, array<string, mixed>> $items */
        $items = $data['items'];

        return new self(
            name: (string) $data['name'],
            description: array_key_exists('description', $data) ? $data['description'] : null,
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            items: array_map(TaskTemplateItemData::fromValidated(...), $items),
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
            'is_active' => $this->isActive,
        ];
    }
}
