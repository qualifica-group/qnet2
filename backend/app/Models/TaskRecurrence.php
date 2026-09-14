<?php

namespace App\Models;

use App\Enums\TaskRecurrenceEnd;
use App\Enums\TaskRecurrenceFrequency;
use App\Models\Abstracts\BaseModel;
use Database\Factories\TaskRecurrenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The recurrence RULE a Task series shares (spec 0120, D-1/D-3): one row per
 * series, never per occurrence. `tasks()` is every Task linked to this
 * series — the capostipite (D-4) AND every occurrence the scheduler
 * materialized (App\Console\Commands\GenerateTaskRecurrences), in no
 * particular order; callers that need the capostipite specifically pick the
 * oldest by id (it is the only Task that existed before the series did).
 *
 * `weekdays` is a plain JSON array of ISO-8601 weekdays (Monday = 1), never
 * an enum column — it is a SET, not a single classification. `generated_until`
 * is server-managed by the command alone (never client-writable: no
 * FormRequest ever exposes it), fillable purely for that internal write.
 */
#[Fillable(['frequency', 'interval', 'weekdays', 'month_day', 'ends', 'ends_on', 'occurrence_count', 'generated_until'])]
class TaskRecurrence extends BaseModel
{
    /** @use HasFactory<TaskRecurrenceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'frequency' => TaskRecurrenceFrequency::class,
            'interval' => 'int',
            'weekdays' => 'array',
            'month_day' => 'int',
            'ends' => TaskRecurrenceEnd::class,
            'ends_on' => 'date:Y-m-d',
            'occurrence_count' => 'int',
            'generated_until' => 'date:Y-m-d',
        ];
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
