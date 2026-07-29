<?php

namespace App\Enums;

/**
 * The discriminant of `quote_lines.line_type` (spec 0065, D-11): revenue
 * lines and cost lines share ONE table, distinguished only by this column —
 * same columns, same validator, same FE component on both the Offerta and
 * Costi tabs. Plain enum (no App\Enums\Concerns\HasMeta): unlike ProductType,
 * this value is never surfaced as a standalone client select
 * (`config/config.php` form_enums) — it is derived server-side from which
 * request array (`offer_lines`/`cost_lines`) a line came from.
 */
enum QuoteLineType: string
{
    case Revenue = 'REVENUE';
    case Cost = 'COST';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
