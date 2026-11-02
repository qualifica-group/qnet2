<?php

namespace Database\Seeders;

use App\Models\SupportTopic;
use App\Models\TaskCategory;
use App\Models\TaskImportance;
use App\Models\TaskPriority;
use App\Models\TaskType;
use App\Models\User;
use Database\Seeders\QualificaCatalog\SupportTopicCatalogue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * The client's IT support request types (spec 0334), all assigned to the IT
 * contact of SupportTopicCatalogue. Create-only on the natural `name` key: a
 * type already there, edited or not from Configurazione, is left alone.
 *
 * Needs QualificaTaskTaxonomySeeder's lookups (a missing one stays empty) and
 * the assignee's account (TestUsersSeeder): without it nothing is created,
 * since a type with no assignee could never be offered to a user.
 */
class QualificaSupportTopicSeeder extends Seeder
{
    private const int SORT_STEP = 10;

    public function run(): void
    {
        // Step 1: the assignee every type needs.
        $assignee = User::query()->where('email', SupportTopicCatalogue::ASSIGNEE_EMAIL)->first();

        if ($assignee === null) {
            $this->command?->warn(sprintf('%s: no account, IT support types skipped.', SupportTopicCatalogue::ASSIGNEE_EMAIL));

            return;
        }

        // Step 2: the Task values every request is created with.
        $defaults = $this->taskDefaults();

        // Step 3: each missing type, with its assignee.
        foreach (SupportTopicCatalogue::TOPICS as $index => [$name, $description, $icon]) {
            if (SupportTopic::query()->where('name', $name)->exists()) {
                continue;
            }

            $topic = SupportTopic::query()->create([
                'name' => $name,
                'description' => $description,
                'icon' => $icon,
                'sort_order' => ($index + 1) * self::SORT_STEP,
                'is_active' => true,
                ...$defaults,
            ]);

            $topic->assignees()->sync([$assignee->id]);
        }
    }

    /**
     * @return array<string, int|bool|null>
     */
    private function taskDefaults(): array
    {
        return [
            'task_type_id' => $this->lookupId(TaskType::class, SupportTopicCatalogue::TASK_TYPE),
            'task_category_id' => $this->lookupId(TaskCategory::class, SupportTopicCatalogue::TASK_CATEGORY),
            'task_priority_id' => $this->lookupId(TaskPriority::class, SupportTopicCatalogue::TASK_PRIORITY),
            'urgent_task_priority_id' => $this->lookupId(TaskPriority::class, SupportTopicCatalogue::URGENT_TASK_PRIORITY),
            'task_importance_id' => $this->lookupId(TaskImportance::class, SupportTopicCatalogue::TASK_IMPORTANCE),
            'due_days' => SupportTopicCatalogue::DUE_DAYS,
            'urgent_due_days' => SupportTopicCatalogue::URGENT_DUE_DAYS,
            'is_private' => false,
            'requires_closure_feedback' => false,
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function lookupId(string $modelClass, string $name): ?int
    {
        $id = $modelClass::query()->where('name', $name)->value('id');

        if ($id === null) {
            $this->command?->warn(sprintf('%s "%s" not found, left empty on the IT support types.', class_basename($modelClass), $name));
        }

        return $id;
    }
}
