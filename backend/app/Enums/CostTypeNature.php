<?php

namespace App\Enums;

use App\Enums\Attributes\Color;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Nature of a cost type (spec 0249, D-1): fixed list in code. DIRECT = chargeable
 * to a work order, INDIRECT = production cost to be apportioned, GENERAL =
 * structure/overhead. The `#[Label]` strings are a server-side fallback only:
 * the grid badge and the select localize through the frontend
 * `enums.cost_type_nature` resources (config/config.php form_enums).
 */
enum CostTypeNature: string
{
    use HasMeta;

    #[Label('Direct')]
    #[Color('blue')]
    case Direct = 'direct';

    #[Label('Indirect')]
    #[Color('amber')]
    case Indirect = 'indirect';

    #[Label('General')]
    #[Color('slate')]
    case General = 'general';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
