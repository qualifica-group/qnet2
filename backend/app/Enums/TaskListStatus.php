<?php

namespace App\Enums;

use App\Enums\Attributes\IsDefault;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * The "Stato" advanced filter of the Task table (spec 0147, D-2/D-3), plus
 * `in_validation` (spec 0151, D-5): the dashboard's "to validate" chip
 * narrows the same `status` filter to this one value. `open` groups every
 * non-closing phase of App\Enums\TaskStatusGroup; `all` lifts the
 * restriction. Presentation labels are resolved by the frontend from
 * `enums.task_list_status`; the `#[Label]` strings are a server-side fallback.
 */
enum TaskListStatus: string
{
    use HasMeta;

    #[Label('Open')]
    #[IsDefault(true)]
    case Open = 'open';

    #[Label('Completed')]
    case Completed = 'completed';

    #[Label('Blocked')]
    case Blocked = 'blocked';

    #[Label('In validation')]
    case InValidation = 'in_validation';

    #[Label('All')]
    case All = 'all';

    /**
     * The status phases a task in this bucket sits in; null when the bucket is
     * not phase-based (`blocked`, `all`).
     *
     * @return array<int, string>|null
     */
    public function groups(): ?array
    {
        return match ($this) {
            self::Open => [TaskStatusGroup::Open->value, TaskStatusGroup::Pending->value, TaskStatusGroup::InValidation->value],
            self::Completed => [TaskStatusGroup::ClosedPositive->value, TaskStatusGroup::ClosedNegative->value],
            self::InValidation => [TaskStatusGroup::InValidation->value],
            default => null,
        };
    }
}
