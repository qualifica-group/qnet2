<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * The "Tipo commessa" discriminant of `work_orders.type` (spec 0093, D-10).
 * Enum of the WorkOrder module, deliberately NOT shared with any other
 * module (same motivation as spec 0072 D-5): reusing another module's enum
 * would couple the two.
 *
 * HasMeta (mirrors ProductType): `::options()` backs BOTH the create-form
 * select (`config('config.form_enums').work_order_type`, GET /api/config)
 * and the `type` grid column's badge metadata
 * (WorkOrdersTableDefinition::badgesFor()/enumKeyFor() -> `work_order_type`).
 * The `#[Label]` strings here are a server-side fallback only: the frontend
 * badge/select both resolve their real, localized label from their OWN i18n
 * `workOrders`/`enums.work_order_type` resources, never from this value.
 */
enum WorkOrderType: string
{
    use HasMeta;

    #[Label('Processing')]
    case Processing = 'processing';

    #[Label('Project')]
    case Project = 'project';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
