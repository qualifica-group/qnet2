<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * The "Scadenza" advanced filter of the Task table (spec 0147, D-3), matched
 * on `COALESCE(end_date, start_date)` exactly like the work-order Task board.
 * Leaving the filter empty means "every due date".
 */
enum TaskDueWindow: string
{
    use HasMeta;

    #[Label('Today')]
    case Today = 'today';

    #[Label('Overdue')]
    case Overdue = 'overdue';

    #[Label('This week')]
    case ThisWeek = 'this_week';
}
