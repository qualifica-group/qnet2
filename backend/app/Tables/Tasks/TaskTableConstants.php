<?php

declare(strict_types=1);

namespace App\Tables\Tasks;

/**
 * The tasks grid's magic values with no natural home in a single existing
 * class (engineering.md §6, no inline literals): TasksTableDefinition,
 * TaskDerivedColumnResolver and TaskRowActionResolver all read from this
 * ONE list rather than repeating it.
 */
final class TaskTableConstants
{
    /**
     * `completion_percentage` has no `tasks` column behind it (spec 0156,
     * D-6): both applyDerivedFilter()/applyDerivedSort() branch on this id
     * before falling through to the aggregate/relation delegates.
     */
    public const string COMPLETION_PERCENTAGE_COLUMN = 'completion_percentage';

    /**
     * Per-block cap for `tasks`, five times the shared
     * `BaseApiController::MAX_LIMIT`. Introduced by spec 0157 for the
     * one-shot Kanban load; since spec 0164 the Kanban pages each column in
     * blocks of 50, so this is only the ceiling a single request may ask
     * for.
     */
    public const int MAX_ROWS_LIMIT = 500;

    /**
     * The seven domain-action flags of TasksAuthorization::actionPermissions()
     * exposed as row actions (spec 0156, D-5): the action key IS the flag
     * key for all seven, so a single loop maps them — never a second
     * evaluation of the matrix TaskCompletionService/TaskActionService
     * themselves re-assert.
     *
     * @var array<int, string>
     */
    public const array DOMAIN_ACTION_FLAGS = ['complete', 'uncomplete', 'approve', 'reject', 'block', 'unblock', 'request_update'];
}
