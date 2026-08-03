<?php

namespace App\Enums;

use App\Enums\Attributes\IsDefault;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * The clock convention a user can pick for every time rendered next to a date.
 *
 * Companion of {@see DateFormatEnum}: same rationale for staying out of
 * GET /api/config (the client owns the rendering), same use as the single
 * source of truth for the accepted `users.time_format` values.
 */
enum TimeFormatEnum: string
{
    use HasMeta;

    /** 24-hour clock, zero-padded (14:30) — the product default. */
    #[Label('24h')]
    #[IsDefault(true)]
    case H24 = '24h';

    /** 12-hour clock with an AM/PM suffix (2:30 PM). */
    #[Label('12h')]
    case H12 = '12h';

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
