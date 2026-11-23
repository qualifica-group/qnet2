<?php

namespace Database\Factories;

use App\Models\DocumentChecklist;
use App\Models\DocumentChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentChecklistItem>
 */
class DocumentChecklistItemFactory extends Factory
{
    protected $model = DocumentChecklistItem::class;

    private static int $nextPosition = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_checklist_id' => DocumentChecklist::factory(),
            'position' => self::$nextPosition++,
            'name' => fake()->words(3, true),
            'description' => null,
            'section' => null,
            'is_required' => true,
            'allowed_file_types' => ['pdf'],
            'max_file_size_kb' => 10240,
            'min_files' => 1,
            'max_files' => 1,
            'validation_prompt' => null,
            'max_age_days' => null,
        ];
    }
}
