<?php

namespace App\Enums;

use App\Enums\Attributes\Color;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Direction of the Supplier commission of a product typology (spec 0202,
 * D-2/D-7): RECEIVED = collected by us (it IS the line's revenue), PAID = a
 * cost towards the supplier. Frozen onto the REVENUE quote line at creation
 * (`quote_lines.supplier_commission_direction`); null means the Supplier
 * commission is not calculated at all.
 */
enum SupplierCommissionDirection: string
{
    use HasMeta;

    #[Label('commission_configurations.directions.received')]
    #[Color('emerald')]
    case Received = 'RECEIVED';

    #[Label('commission_configurations.directions.paid')]
    #[Color('amber')]
    case Paid = 'PAID';
}
