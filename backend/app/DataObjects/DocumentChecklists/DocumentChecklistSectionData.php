<?php

declare(strict_types=1);

namespace App\DataObjects\DocumentChecklists;

/**
 * One validated section (document group) of a save payload (spec 0427). `id` null
 * means a new section; the array position of the section becomes its `position`.
 */
final readonly class DocumentChecklistSectionData
{
    public function __construct(
        public ?int $id,
        public string $name,
        public ?string $description,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            name: (string) $data['name'],
            description: $data['description'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(int $position): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'position' => $position,
        ];
    }
}
