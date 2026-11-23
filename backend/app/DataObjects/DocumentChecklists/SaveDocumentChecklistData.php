<?php

declare(strict_types=1);

namespace App\DataObjects\DocumentChecklists;

/**
 * Validated payload of POST/PUT /api/document-checklists (spec 0403): both are a
 * full replacement of the checklist and of its items, so one DTO serves both.
 */
final readonly class SaveDocumentChecklistData
{
    /**
     * @param  array<int, DocumentChecklistItemData>  $items  in display order
     */
    public function __construct(
        public string $name,
        public string $code,
        public ?string $description,
        public bool $isActive,
        public ?int $categoryId,
        public array $items,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            code: (string) $data['code'],
            description: $data['description'] ?? null,
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            categoryId: isset($data['category_id']) ? (int) $data['category_id'] : null,
            items: array_map(
                DocumentChecklistItemData::fromValidated(...),
                array_values($data['items'] ?? []),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'is_active' => $this->isActive,
            'category_id' => $this->categoryId,
        ];
    }
}
