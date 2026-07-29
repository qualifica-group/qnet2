<?php

namespace App\Enums;

use App\Enums\Attributes\Color;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

enum CommissionType: string
{
    use HasMeta;

    #[Label('commission_configurations.types.fixed_amount')]
    #[Color('amber')]
    case FixedAmount = 'FIXED_AMOUNT';

    #[Label('commission_configurations.types.percentage')]
    #[Color('blue')]
    case Percentage = 'PERCENTAGE';
}
