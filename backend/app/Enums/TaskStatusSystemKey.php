<?php

namespace App\Enums;

/**
 * The PROTECTED rows of the Task status configurator (spec 0101, D-5 as
 * rectified by the user directive 2026-09-04; spec 0116 D-4 reinstates
 * `in_progress`; spec 0118 D-4/D-5 adds `assigned` — see both below),
 * persisted as `task_statuses.system_key` — UNIQUE, and NULL on every
 * ordinary row. A row carrying one of these keys cannot be deleted and
 * accepts only the changes App\Services\Statuses\SystemStatusGuard allows.
 *
 * The original four are the minimum the module needs to stay usable whatever
 * the admin configures: a Task must always have a status to be opened in,
 * one to resume on when reopened, and one to be closed in on each outcome.
 * Everything else — how many working or validation steps sit in between, and
 * what they are called — is ordinary configuration.
 *
 * NOT the phase set: that is App\Enums\TaskStatusGroup (`task_statuses.group`),
 * a MANY-to-one classification. `system_key` is UNIQUE and could never carry
 * it — the reason the two are separate columns, exactly as in
 * `contract_statuses`. `pending` and `in_validation`, dropped by spec 0101
 * D-5 (2026-09-04), stay retired: they were phases mislabelled as system
 * keys and live on as TaskStatusGroup cases.
 *
 * `in_progress` IS BACK, for a DIFFERENT reason than the one it was dropped
 * for — read this before "correcting" it again. Spec 0101 D-5 retired it
 * because it was being used as a CLASSIFICATION (a phase travelling under a
 * `system_key`), and `system_key` is UNIQUE so it cannot classify many rows
 * to one phase. Spec 0116 D-4 reinstates it for the opposite job: DESIGNATING
 * A SINGLE ROW — the Task's resume status, the target the reopening actions
 * (uncomplete, reject) land on — which is exactly what `system_key` is for.
 * The phase still lives on `group` alone (`open`, here). The two uses do not
 * conflict; they were never the same use.
 *
 * `Assigned` DESIGNATES A SINGLE ROW TOO (spec 0118, D-4/D-5): the landing
 * status App\Services\Tasks\TaskInitialStatusResolver derives for a
 * brand-new Task whose derivation does not qualify for `open` — two or more
 * assignees, or a single one who is neither the creator nor the requester.
 * The row itself ("Assegnato") already existed as an ordinary status; it is
 * PROMOTED in place by 2026_09_11_110000_designate_assigned_task_status, the
 * same two-path precedent 2026_09_11_100000 set for `in_progress`. The
 * protected rows this enum designates are now five.
 *
 * Never mass-assignable: the rows are created by the migrations, and only
 * SystemStatusGuard protects them afterwards.
 */
enum TaskStatusSystemKey: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Assigned = 'assigned';
    case ClosedPositive = 'closed_positive';
    case ClosedNegative = 'closed_negative';
}
