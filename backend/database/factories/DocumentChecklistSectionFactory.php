<?php

namespace Database\Factories;

use App\Models\DocumentChecklist;
use App\Models\DocumentChecklistSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentChecklistSection>
 */
class DocumentChecklistSectionFactory extends Factory
{
    protected $model = DocumentChecklistSection::class;

    private static int $nextPosition = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_checklist_id' => DocumentChecklist::factory(),
            'name' => fake()->unique()->words(2, true),
            'description' => null,
            'position' => self::$nextPosition++,
        ];
    }
}
