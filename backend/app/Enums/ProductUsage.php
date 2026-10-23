<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Where a product may be used inside an Offerta (spec 0142): SALE puts it on
 * the Prodotti tab (REVENUE lines), COST on the Costi tab (COST lines). The
 * two are independent; INTERNAL (spec 0307) marks a physical good the company
 * keeps for itself and tracks in stock. A product stores a SET of these cases
 * in `products.usages`, never a single one.
 */
enum ProductUsage: string
{
    use HasMeta;

    #[Label('Sellable')]
    case Sale = 'SALE';

    #[Label('Usable as cost')]
    case Cost = 'COST';

    #[Label('Internal use')]
    case Internal = 'INTERNAL';

    /**
     * The usage a quote line tab requires (spec 0142, D-1).
     */
    public static function forLineType(QuoteLineType $type): self
    {
        return match ($type) {
            QuoteLineType::Revenue => self::Sale,
            QuoteLineType::Cost => self::Cost,
        };
    }
}
