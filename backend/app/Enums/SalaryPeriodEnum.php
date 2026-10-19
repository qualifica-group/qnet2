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

    private const int MONTHS_PER_YEAR = 12;

    /** Working hours one salary amount of this period pays (spec 0277 D-1). */
    public function hours(): int
    {
        $monthly = (int) config('employment.monthly_salary_hours');

        return match ($this) {
            self::Hourly => 1,
            self::Monthly => $monthly,
            self::Yearly => $monthly * self::MONTHS_PER_YEAR,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
