<?php

declare(strict_types=1);

namespace App\Enums;

/** What a team member did to an Offerta, a Contratto or a Commessa (spec 0413). */
enum TeamActivityEventEnum: string
{
    case Created = 'created';
    case StatusChanged = 'status_changed';
}
