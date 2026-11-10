<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * The "Assegnazione" advanced filter of the Commesse page, relative to the
 * acting user, OR-combined like TaskAssignmentScope. `visible` lifts the role
 * restriction: only WorkOrderVisibilityScope still applies.
 */
enum WorkOrderAssignmentScope: string
{
    use HasMeta;

    #[Label('Supervisor')]
    case Supervisor = 'supervisor';

    #[Label('Participant')]
    case Participant = 'participant';

    #[Label('All visible')]
    case Visible = 'visible';
}
