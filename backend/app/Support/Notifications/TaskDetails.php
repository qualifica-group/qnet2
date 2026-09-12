<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use App\Models\Task;
use App\Models\User;

/**
 * The "scheda dettagli" of a Task notification email (spec 0119, D-8): the
 * Task gemello of RecordDetails, which could not be reused because it
 * dispatches on AssignmentTargetEnum (Registry/Opportunity) and knows nothing
 * about a Task.
 *
 * Returns i18n KEY => rendered value. The keys are translated downstream by
 * DetailsTable, inside the RECIPIENT's locale (User implements
 * HasLocalePreference), never in the locale of whoever performed the write.
 *
 * The in-app notification does NOT carry this table: NotificationData::message
 * stays one sentence, because the bell renders a line of text, not a grid.
 */
final class TaskDetails
{
    /**
     * Everything the table reads, eager-loaded in one go. `assignees` is a
     * pivot: without it here, `Model::preventLazyLoading()` would throw.
     *
     * @var array<int, string>
     */
    private const array RELATIONS = ['taskStatus', 'taskPriority', 'requester', 'assignees'];

    /**
     * @return array<string, string>
     */
    public static function for(Task $task): array
    {
        $task->loadMissing(self::RELATIONS);

        return self::compact([
            'notifications.fields.title' => $task->title,
            'notifications.fields.status' => $task->taskStatus?->name,
            'notifications.fields.priority' => $task->taskPriority?->name,
            'notifications.fields.end_date' => $task->end_date?->format('d/m/Y'),
            'notifications.fields.requester' => $task->requester?->name,
            'notifications.fields.assignees' => self::nameList($task->assignees),
        ]);
    }

    /**
     * An unordered set, joined in the order the relation already imposes
     * (`orderBy('users.name')` on Task::assignees).
     *
     * @param  iterable<int, User>  $users
     */
    private static function nameList(iterable $users): ?string
    {
        $names = [];

        foreach ($users as $user) {
            $names[] = $user->name;
        }

        return $names === [] ? null : implode(', ', $names);
    }

    /**
     * Drops the empty rows so a Task without a priority shows a shorter
     * table rather than a row with a blank cell (AC-029).
     *
     * Deliberately NOT shared with RecordDetails::compact(): hoisting six
     * lines into a trait would mean editing a class this spec has no business
     * touching, for no gain a reader would notice.
     *
     * @param  array<string, string|null>  $fields
     * @return array<string, string>
     */
    private static function compact(array $fields): array
    {
        $details = [];

        foreach ($fields as $label => $value) {
            if ($value !== null && trim($value) !== '') {
                $details[$label] = trim($value);
            }
        }

        return $details;
    }
}
