<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\TimeEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Time entry (spec 0122, D-4): a single segnatempo row, owned by the user who
 * logged it. With `task_id` set, the server IMPOSES `title` and the three
 * record links from the Task (D-5) — this model stores whatever it is given
 * by the write path, the override itself lives in the Service layer.
 */
#[Fillable([
    'user_id',
    'date',
    'title',
    'task_type_id',
    'start_time',
    'end_time',
    'minutes',
    'notes',
    'registry_id',
    'opportunity_id',
    'work_order_id',
    'task_id',
])]
class TimeEntry extends BaseModel
{
    /** @use HasFactory<TimeEntryFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'minutes' => 'int',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<TaskType, $this>
     */
    public function taskType(): BelongsTo
    {
        return $this->belongsTo(TaskType::class);
    }

    /**
     * @return BelongsTo<Registry, $this>
     */
    public function registry(): BelongsTo
    {
        return $this->belongsTo(Registry::class);
    }

    /**
     * @return BelongsTo<Opportunity, $this>
     */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
