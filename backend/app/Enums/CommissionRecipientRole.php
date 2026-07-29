<?php

namespace App\Enums;

use App\Enums\Attributes\Color;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

enum CommissionRecipientRole: string
{
    use HasMeta;

    #[Label('commission_configurations.roles.commercial')]
    #[Color('blue')]
    case Commercial = 'COMMERCIAL';

    #[Label('commission_configurations.roles.reporter')]
    #[Color('violet')]
    case Reporter = 'REPORTER';

    #[Label('commission_configurations.roles.supervisor')]
    #[Color('amber')]
    case Supervisor = 'SUPERVISOR';

    #[Label('commission_configurations.roles.supplier')]
    #[Color('emerald')]
    case Supplier = 'SUPPLIER';
}
