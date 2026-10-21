<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Working time of an employment condition (spec 0291 D-3). Single source of
 * truth for the model casts, the contract request validation and the grid
 * column's set-filter options/enumKey.
 */
enum WorkingTimeEnum: string
{
    use HasMeta;

    #[Label('Full time')]
    case FullTime = 'full_time';

    #[Label('Part time')]
    case PartTime = 'part_time';

    /**
     * The supported values (the `value` of every case), for validation rules
     * and option lists.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
