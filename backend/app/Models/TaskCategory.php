<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\TaskCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Task category lookup entity (spec 0101, D-4): one of the four PURE Task
 * configurators — no `system_key`, no numeric weight, no protected row.
 * Every row is renameable/deletable, guarded only by "in use" by a Task
 * (D-8). `color` is a token of `App\Support\BadgeTokens::colors()`, `icon` a
 * name of `App\Support\BadgeTokens::icons()`, both validated server-side by
 * the FormRequest — never free text.
 */
#[Fillable(['name', 'description', 'color', 'icon', 'sort_order', 'is_active'])]
class TaskCategory extends BaseModel
{
    /** @use HasFactory<TaskCategoryFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'int',
            'is_active' => 'bool',
        ];
    }

    /**
     * The Tasks classified under this category — the "in use" set
     * `TaskCategoryService::delete()` guards against (D-8b).
     *
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
