<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\TaskStatusGroup;
use App\Models\TaskStatus;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The task_status_id a `task_templates` row carries must be an ACTIVE status
 * in the `open` or `pending` phase (spec 0124, D-4, AC-003) — REPLACES a
 * plain `exists:task_statuses,id` on `items.*.task_status_id` entirely, it
 * does not sit next to it, mirroring App\Rules\SelectableProductCategory.
 *
 * A blank value passes (the field is nullable — D-4's own "unset" case,
 * resolved at generation time by TaskInitialStatusResolver).
 */
final class TaskTemplateItemStatus implements ValidationRule
{
    /**
     * @param  Closure(string): void  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_numeric($value)) {
            $fail(__('The selected task status is invalid.'));

            return;
        }

        /** @var TaskStatus|null $status */
        $status = TaskStatus::query()->find((int) $value, ['id', 'is_active', 'group']);

        if ($status === null) {
            $fail(__('The selected task status is invalid.'));

            return;
        }

        $allowedGroup = in_array($status->group, [TaskStatusGroup::Open, TaskStatusGroup::Pending], true);

        if (! $status->is_active || ! $allowedGroup) {
            $fail(__('The selected task status must be active and in the open or pending group.'));
        }
    }
}
