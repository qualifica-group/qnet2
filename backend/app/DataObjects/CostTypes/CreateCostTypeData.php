<?php

namespace App\DataObjects\CostTypes;

use App\Enums\CostTypeNature;

/**
 * Validated payload for creating a cost type (POST /api/cost-types, specs 0210,
 * 0249). `sort_order` is server-managed (CostTypeService places a new row last).
 */
final readonly class CreateCostTypeData
{
    public function __construct(
        public string $name,
        public ?string $description,
        public CostTypeNature $nature,
        public string $color,
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
            nature: CostTypeNature::from((string) $data['nature']),
            color: (string) $data['color'],
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
            'nature' => $this->nature,
            'color' => $this->color,
            'is_active' => $this->isActive,
        ];
    }
}
