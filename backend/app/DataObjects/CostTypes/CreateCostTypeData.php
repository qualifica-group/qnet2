<?php

namespace App\DataObjects\CostTypes;

/**
 * Validated payload for creating a cost type (POST /api/cost-types, spec 0210).
 */
final readonly class CreateCostTypeData
{
    public function __construct(
        public string $name,
        public ?string $description,
        public int $sortOrder,
        public bool $isActive,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            description: $data['description'] ?? null,
            sortOrder: (int) ($data['sort_order'] ?? 0),
            isActive: (bool) ($data['is_active'] ?? true),
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
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
        ];
    }
}
