<?php

namespace App\Enums;

use App\Enums\Attributes\IsDefault;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Kind of product (spec 0017): a top-level classification stored in the
 * products.product_type column. SERVICE is the default, PHYSICAL (spec 0307) is
 * the only type that may be tracked in stock — this enum is the single point of extension: adding a case here surfaces
 * it in the model cast, the products grid badge and the client select
 * (config/config.php form_enums -> `product_type`) with no other change.
 */
enum ProductType: string
{
    use HasMeta;

    #[Label('Service')]
    #[IsDefault(true)]
    case Service = 'SERVICE';

    #[Label('Physical good')]
    case Physical = 'PHYSICAL';
}
