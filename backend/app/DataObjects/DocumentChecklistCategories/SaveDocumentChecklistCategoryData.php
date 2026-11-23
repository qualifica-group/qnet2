<?php

declare(strict_types=1);

namespace App\DataObjects\DocumentChecklistCategories;

/**
 * Validated payload of POST/PUT /api/document-checklist-categories (spec 0410):
 * both are a full replacement, so one DTO serves both.
 */
final readonly class SaveDocumentChecklistCategoryData
{
    public function __construct(
        public string $name,
        public ?string $description,
        public int $sortOrder,
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
        ];
    }
}
