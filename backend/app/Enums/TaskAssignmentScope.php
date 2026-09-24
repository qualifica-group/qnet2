<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * The "Assegnazione" advanced filter of the Task table (spec 0147, D-3;
 * spec 0153, D-1), relative to the acting user. The filter is now REQUIRED
 * and MULTI-VALUE, combined in OR (TaskAdvancedFilterApplier::applyAssignment):
 * an omitted value falls back to `[assigned_to_me]`, never to "everyone".
 *
 * `all` (spec 0153, D-1) is the union of every role — requester, assignee,
 * watcher, creator — applied even for an actor holding `tasks.viewAll`: that
 * permission only lifts TaskVisibilityScope's OWN restriction, it never
 * exempts a task from the advanced filter stacked on top of it. `visible`
 * is the one value that lifts the role restriction entirely.
 *
 * Spec 0151 D-3 adds three EXCLUSIVE dashboard buckets, applied by the SAME
 * class:
 * - `assigned_by_me`: requester = actor AND actor NOT an assignee (the
 *   requester who handed the task off, distinct from `assigned_to_me`).
 * - `created_by_me` (spec 0153, D-3): creator = actor, and actor is NEITHER
 *   requester NOR assignee NOR watcher — a creator who also holds one of the
 *   other three roles counts under that role instead, never twice.
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

    /** Union of every role (spec 0153, D-1): "task in cui ho un ruolo". */
    #[Label('All')]
    case All = 'all';

    /**
     * No role restriction (spec 0153, D-1, user decision 2026-09-24): only
     * TaskVisibilityScope applies, so `tasks.viewAll`/`tasks.viewSite` keep
     * listing their colleagues' tasks. For any other actor it coincides with
     * `all`, so it needs no gate of its own.
     */
    #[Label('All visible')]
    case Visible = 'visible';
}
