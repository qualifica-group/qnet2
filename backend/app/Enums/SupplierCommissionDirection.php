<?php

namespace App\Enums;

use App\Enums\Attributes\Color;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Direction of a Supplier commission (spec 0306, D-1): RECEIVED = collected by
 * us (it IS the line's revenue), PAID = a cost towards the supplier. Chosen on
 * the SUPPLIER commission rule (or on a manual Supplier commission of an offer)
 * and frozen onto the REVENUE quote line (`quote_lines.supplier_commission_direction`);
 * null on the line means the typology switches the Supplier commission off.
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
