<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Period a contract salary amount refers to (spec 0272 D-4).
 */
enum SalaryPeriodEnum: string
{
    use HasMeta;

    #[Label('Monthly')]
    case Monthly = 'monthly';

    #[Label('Yearly')]
    case Yearly = 'yearly';

    #[Label('Hourly')]
    case Hourly = 'hourly';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
