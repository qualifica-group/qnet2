<?php

namespace App\Enums;

use App\Enums\Attributes\Color;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

enum CommissionConfigurationStatus: string
{
    use HasMeta;

    #[Label('commission_configurations.statuses.active')]
    #[Color('green')]
    case Active = 'ACTIVE';

    #[Label('commission_configurations.statuses.suspended')]
    #[Color('slate')]
    case Suspended = 'SUSPENDED';
}
