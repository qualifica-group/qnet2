<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which slot a user was assigned to (spec 0081): the `supervisor_id` column
 * or a "Gestore Account" pivot row. Internal only, no UI metadata.
 *
 * The two are NOT interchangeable and neither implies the other: the same
 * user can hold both on the same record and then receives one notification
 * per role.
 */
enum AssignmentRoleEnum: string
{
    case Supervisor = 'SUPERVISOR';

    case Manager = 'MANAGER';
}
