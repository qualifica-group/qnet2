<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * The "Assegnazione" advanced filter of the Task table (spec 0147, D-3),
 * relative to the acting user. Leaving the filter empty means "everyone".
 */
enum TaskAssignmentScope: string
{
    use HasMeta;

    #[Label('Assigned to me')]
    case AssignedToMe = 'assigned_to_me';

    #[Label('Requested by me')]
    case RequestedByMe = 'requested_by_me';
}
