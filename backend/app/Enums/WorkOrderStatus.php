<?php

namespace App\Enums;

use App\Enums\Attributes\Color;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * The 4 values `App\Services\WorkOrders\WorkOrderStatusResolver` ever
 * produces (spec 0149, D-1): NEVER persisted, NEVER a column, resolved in
 * read from `is_force_closed` and the progress of the commessa's own ROOT
 * tasks.
 *
 * HasMeta (mirrors WorkOrderType): `::options()` backs the `status` grid
 * column's badge metadata (WorkOrdersTableDefinition::badgesFor()/
 * enumKeyFor() -> `work_order_status`) and is ALSO registered under
 * `config('config.form_enums')` (GET /api/config) even though `status` is
 * never a form field — a plain, non-sensitive presentation catalogue. The
 * `#[Label]` strings are a server-side fallback only: the frontend badge
 * resolves its real, localized label from its own `enums.work_order_status`
 * i18n resource. `#[Color]` (D-9) drives the grid badge colour.
 */
enum WorkOrderStatus: string
{
    use HasMeta;

    #[Label('Open')]
    #[Color('blue')]
    case Open = 'open';

    #[Label('In progress')]
    #[Color('amber')]
    case InProgress = 'in_progress';

    #[Label('Completed')]
    #[Color('green')]
    case Completed = 'completed';

    #[Label('Closed')]
    #[Color('slate')]
    case Closed = 'closed';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
