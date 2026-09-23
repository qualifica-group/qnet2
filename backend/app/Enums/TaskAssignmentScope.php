<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * The "Assegnazione" advanced filter of the Task table (spec 0147, D-3),
 * relative to the acting user. Leaving the filter empty means "everyone".
 *
 * Spec 0151 D-3 adds three EXCLUSIVE dashboard buckets, applied by
 * App\Tables\Tasks\TaskAdvancedFilterApplier:
 * - `assigned_by_me`: requester = actor AND actor NOT an assignee (the
 *   requester who handed the task off, distinct from `assigned_to_me`).
 * - `created_by_me`: creator = actor AND (requester is null OR requester !=
 *   actor) — a creator who is also the requester counts as `assigned_by_me`
 *   or `assigned_to_me`, never twice.
 * - `observed_by_me`: actor is a watcher.
 */
enum TaskAssignmentScope: string
{
    use HasMeta;

    #[Label('Assigned to me')]
    case AssignedToMe = 'assigned_to_me';

    #[Label('Requested by me')]
    case RequestedByMe = 'requested_by_me';

    #[Label('Assigned by me')]
    case AssignedByMe = 'assigned_by_me';

    #[Label('Created by me')]
    case CreatedByMe = 'created_by_me';

    #[Label('Observed by me')]
    case ObservedByMe = 'observed_by_me';
}
