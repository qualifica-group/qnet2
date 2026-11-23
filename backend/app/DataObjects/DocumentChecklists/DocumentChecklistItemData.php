<?php

declare(strict_types=1);

namespace App\DataObjects\DocumentChecklists;

/**
 * One validated checklist item of a save payload (spec 0403). `id` null means a
 * new item; the array position of the item in the payload becomes its `position`;
 * `sectionIndex` points into the sections of the same payload (spec 0427).
 */
final readonly class DocumentChecklistItemData
{
    /**
     * @param  array<int, string>  $allowedFileTypes
     */
    public function __construct(
        public ?int $id,
        public string $name,
        public ?string $description,
        public ?int $sectionIndex,
        public bool $isRequired,
        public array $allowedFileTypes,
        public int $maxFileSizeKb,
        public int $minFiles,
        public int $maxFiles,
        public ?string $validationPrompt,
        public ?int $maxAgeDays,
        public ?string $dateField,
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
            sectionIndex: isset($data['section_index']) ? (int) $data['section_index'] : null,
            isRequired: (bool) $data['is_required'],
            allowedFileTypes: array_values($data['allowed_file_types']),
            maxFileSizeKb: (int) $data['max_file_size_kb'],
            minFiles: (int) $data['min_files'],
            maxFiles: (int) $data['max_files'],
            validationPrompt: $data['validation_prompt'] ?? null,
            maxAgeDays: isset($data['max_age_days']) ? (int) $data['max_age_days'] : null,
            dateField: $data['date_field'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(int $position, ?int $sectionId): array
    {
        return [
            'position' => $position,
            'name' => $this->name,
            'description' => $this->description,
            'section_id' => $sectionId,
            'is_required' => $this->isRequired,
            'allowed_file_types' => $this->allowedFileTypes,
            'max_file_size_kb' => $this->maxFileSizeKb,
            'min_files' => $this->minFiles,
            'max_files' => $this->maxFiles,
            'validation_prompt' => $this->validationPrompt,
            'max_age_days' => $this->maxAgeDays,
            'date_field' => $this->dateField,
        ];
    }
}
