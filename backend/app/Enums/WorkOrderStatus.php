<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * The 2 values `App\Services\WorkOrders\WorkOrderStatusResolver` ever
 * produces (spec 0093, D-3): NEVER persisted, NEVER a column, resolved in
 * read from `is_force_closed` alone.
 *
 * HasMeta (mirrors WorkOrderType): `::options()` backs the `status` grid
 * column's badge metadata (WorkOrdersTableDefinition::badgesFor()/
 * enumKeyFor() -> `work_order_status`) and is ALSO registered under
 * `config('config.form_enums')` (GET /api/config) even though `status` is
 * never a form field (D-3) — a plain, non-sensitive presentation catalogue
 * a future consumer (e.g. an advanced-filter option list) can read without a
 * second registration. The `#[Label]` strings are a server-side fallback
 * only: the frontend badge resolves its real, localized label from its own
 * `enums.work_order_status` i18n resource.
 */
enum WorkOrderStatus: string
{
    use HasMeta;

    #[Label('Open')]
    case Open = 'open';

    #[Label('Closed')]
    case Closed = 'closed';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
