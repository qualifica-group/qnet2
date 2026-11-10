<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * The "Assegnazione" advanced filter of the Opportunità, Offerte and Contratti
 * pages, relative to the acting user, OR-combined like TaskAssignmentScope.
 * `visible` lifts the role restriction: only the module's visibility scope
 * still applies.
 */
enum CommercialAssignmentScope: string
{
    use HasMeta;

    #[Label('Supervisor')]
    case Supervisor = 'supervisor';

    #[Label('Account manager')]
    case Manager = 'manager';

    #[Label('All visible')]
    case Visible = 'visible';
}
