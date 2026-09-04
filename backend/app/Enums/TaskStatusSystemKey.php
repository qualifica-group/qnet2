<?php

namespace App\Enums;

/**
 * The PROTECTED rows of the Task status configurator (spec 0101, D-5 as
 * rectified by the user directive 2026-09-04), persisted as
 * `task_statuses.system_key` — UNIQUE, and NULL on every ordinary row.
 * A row carrying one of these keys cannot be deleted and accepts only the
 * changes App\Services\Statuses\SystemStatusGuard allows.
 *
 * These three are the minimum the module needs to stay usable whatever the
 * admin configures: a Task must always have a status to be opened in, and
 * one to be closed in on each outcome. Everything else — how many working
 * or validation steps sit in between, and what they are called — is ordinary
 * configuration.
 *
 * NOT the phase set: that is App\Enums\TaskStatusGroup (`task_statuses.group`),
 * a MANY-to-one classification. `system_key` is UNIQUE and could never carry
 * it — the reason the two are separate columns, exactly as in
 * `contract_statuses`. The three keys dropped here (`in_progress`, `pending`,
 * `in_validation`) were phases mislabelled as system keys; they live on as
 * TaskStatusGroup cases.
 *
 * Never mass-assignable: the rows are created by the migration, and only
 * SystemStatusGuard protects them afterwards.
 */
enum TaskStatusSystemKey: string
{
    case Open = 'open';
    case ClosedPositive = 'closed_positive';
    case ClosedNegative = 'closed_negative';
}
