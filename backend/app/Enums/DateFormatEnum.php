<?php

namespace App\Enums;

use App\Enums\Attributes\IsDefault;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;
use App\Http\Requests\Auth\UpdateProfileRequest;

/**
 * The date patterns a user can pick for every date rendered by the UI.
 *
 * Single source of truth for the accepted `users.date_format` values, consumed
 * by {@see UpdateProfileRequest} via {@see values()}.
 * The rendering itself lives on the client (`lib/formatting/date-display.ts`),
 * which is why this enum is NOT published through GET /api/config: the option
 * list must stay in step with the formatter that implements the patterns, same
 * discipline already used for `module_open_preferences` (spec 0042).
 *
 * Labels are the pattern itself ("DD/MM/YYYY"), deliberately locale-independent
 * like LocaleEnum's native language names.
 */
enum DateFormatEnum: string
{
    use HasMeta;

    /** Italian/European day-first pattern — the product default (03/08/2026). */
    #[Label('DD/MM/YYYY')]
    #[IsDefault(true)]
    case Dmy = 'dmy';

    /** US month-first pattern (08/03/2026). */
    #[Label('MM/DD/YYYY')]
    case Mdy = 'mdy';

    /** ISO 8601 calendar date (2026-08-03). */
    #[Label('YYYY-MM-DD')]
    case Ymd = 'ymd';

    /**
     * The accepted values, for validation rules and option lists.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
