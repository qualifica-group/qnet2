<?php

return [
    'GET /time-entries' => [
        'summary' => 'List the time tracking days of a person',
        'description' => 'Returns one item per day of the period for the caller or user_id: target and tracked minutes, utilization, status (no_target, under_target, on_target, over_target), holiday flags, closures, absences, day note and the time entries. Period: date_from/date_to (max 366 days) or period_preset (day, week default, month, year). Filters: task types, registries, opportunities, work orders, tasks, daily_statuses, is_active; sort_by date, target_minutes or is_active; page/per_page. meta holds the caller\'s rights. Requires time-entries.viewAny; another person needs manageAll, viewAll or being their manager (403).',
    ],
    'POST /time-entries' => [
        'summary' => 'Log a time entry',
        'description' => 'Records time worked on date: minutes (1-1440) is required, start_time/end_time (H:i) optional but together; task_type_id must be an active task type. With task_id the title, registry, opportunity, work order and phase are taken from the task (which must be visible to the owner); otherwise title is required, opportunity and work order are mutually exclusive and either fills registry_id when blank. user_id logs for another person and requires time-entries.manageAll. notes are mirrored as a comment on the linked work order. Answers 201 with the entry. Requires time-entries.create.',
    ],
    'PUT /time-entries/day-notes' => [
        'summary' => 'Set the note of a time tracking day',
        'description' => 'Saves the free-text note of one date for the caller, or for user_id (requires time-entries.manageAll). An empty or missing note deletes it. Returns date and note. Requires time-entries.update.',
    ],
    'GET /time-entries/exports/filtered' => [
        'summary' => 'Export the filtered time entries to Excel',
        'description' => 'File download (xlsx): one row per time entry of the selected person and period, applying the same filters and visibility rules as the day list (user_id, period, task types, registries, opportunities, work orders, tasks, daily statuses, is_active). Requires time-entries.export.',
    ],
    'GET /time-entries/exports/monthly' => [
        'summary' => 'Export the monthly time report to Excel',
        'description' => 'File download (xlsx): the time entries of month (1-12) and year, grouped by person, for every user or only user_ids. Requires time-entries.exportMonthly.',
    ],
    'GET /time-entries/stats/overview' => [
        'summary' => 'Get the time tracking totals of a period',
        'description' => 'For the caller or user_id and the same period and filters as the day list: daily and period target minutes (lowered by approved absences), working days, tracked minutes and tracked days, average daily minutes and the count of over-target and under-target working days. Requires time-entries.viewAny; another person needs manageAll, viewAll or being their manager (403).',
    ],
    'GET /time-entries/stats/pulse' => [
        'summary' => 'Get the time distribution by task type',
        'description' => 'For the caller or user_id and the period (day filters ignored): coverage of the working days (percentage of target tracked, working and tracked days) and the minutes grouped by task type, as primary_cluster plus other_clusters. Requires time-entries.viewAny; another person needs manageAll, viewAll or being their manager (403).',
    ],
    'GET /time-entries/stats/team' => [
        'summary' => 'Get the time tracking pulse of a team',
        'description' => 'For the period (date_from/date_to or period_preset), returns the caller\'s active subordinates, or every active user with time-entries.viewAll (is_full_list true): each with user, managers, job roles, roles, business functions, operational site, coverage and main task type cluster. 403 when the caller has no subordinates and lacks viewAll. Requires time-entries.viewAny.',
    ],
    'GET /time-entries/{timeEntry}' => [
        'summary' => 'Get a time entry',
        'description' => 'Returns the time entry with owner, date, title, task type, times, minutes, notes, linked registry, opportunity, work order, task and phase, plus whether the caller may update or delete it. Requires time-entries.view; 403 on another person\'s entry without time-entries.manageAll.',
    ],
    'PUT /time-entries/{timeEntry}' => [
        'summary' => 'Update a time entry',
        'description' => 'Replaces the entry with the submitted values (same fields and rules as creation); the owner cannot change (user_id is refused). With a task the title and links are again taken from the task. Returns the entry. Requires time-entries.update on own entries, time-entries.manageAll for others\'; an entry logged on a task can be changed by whoever may update that task.',
    ],
    'DELETE /time-entries/{timeEntry}' => [
        'summary' => 'Delete a time entry',
        'description' => 'Deletes the time entry and its mirrored work order comment; answers 200 with no data. Requires time-entries.delete on own entries, time-entries.manageAll for others\'; an entry logged on a task can be deleted by whoever may update that task.',
    ],
];
