<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\SupportTopicFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * "Tipo di problema" of the IT support request (spec 0334): the configuration
 * a user's request is turned into a Task from. Assignees/watchers are plain
 * users chosen by the administrator (D-2).
 */
#[Fillable([
    'name', 'description', 'icon', 'sort_order', 'is_active',
    'task_type_id', 'task_category_id', 'task_priority_id', 'urgent_task_priority_id', 'task_importance_id',
    'due_days', 'urgent_due_days', 'is_private', 'requires_closure_feedback',
])]
class SupportTopic extends BaseModel
{
    /** @use HasFactory<SupportTopicFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'int',
            'is_active' => 'bool',
            'due_days' => 'int',
            'urgent_due_days' => 'int',
            'is_private' => 'bool',
            'requires_closure_feedback' => 'bool',
        ];
    }

    /**
     * Topics a user may pick: active, with at least one ACTIVE assignee
     * (otherwise the request would be assigned to nobody).
     *
     * @param  Builder<SupportTopic>  $query
     */
    public function scopeAvailable(Builder $query): void
    {
        $query->where('is_active', true)
            ->whereHas('assignees', fn (Builder $users) => $users->where('users.is_active', true));
    }

    /** @return BelongsTo<TaskType, $this> */
    public function taskType(): BelongsTo
    {
        return $this->belongsTo(TaskType::class);
    }

    /** @return BelongsTo<TaskCategory, $this> */
    public function taskCategory(): BelongsTo
    {
        return $this->belongsTo(TaskCategory::class);
    }

    /** @return BelongsTo<TaskPriority, $this> */
    public function taskPriority(): BelongsTo
    {
        return $this->belongsTo(TaskPriority::class);
    }

    /** @return BelongsTo<TaskPriority, $this> */
    public function urgentTaskPriority(): BelongsTo
    {
        return $this->belongsTo(TaskPriority::class, 'urgent_task_priority_id');
    }

    /** @return BelongsTo<TaskImportance, $this> */
    public function taskImportance(): BelongsTo
    {
        return $this->belongsTo(TaskImportance::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'support_topic_assignee');
    }

    /** @return BelongsToMany<User, $this> */
    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'support_topic_watcher');
    }
}
