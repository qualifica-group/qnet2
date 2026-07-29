<?php

namespace App\Enums;

use App\Enums\Attributes\Color;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

enum CommissionApplicationScope: string
{
    use HasMeta;

    #[Label('commission_configurations.scopes.product_category')]
    #[Color('slate')]
    case ProductCategory = 'PRODUCT_CATEGORY';

    #[Label('commission_configurations.scopes.product')]
    #[Color('blue')]
    case Product = 'PRODUCT';
}
