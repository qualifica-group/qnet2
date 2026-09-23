<?php

namespace App\Enums;

use App\Enums\Attributes\IsDefault;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * The accent palettes a user can pick for the whole UI (Settings → System).
 *
 * Single source of truth for the accepted `users.color_preset` values. Like
 * {@see DateFormatEnum} it stays out of GET /api/config: the colors themselves
 * live in the client stylesheet (`color-presets.css`), so the option list must
 * stay in step with it there.
 */
enum ColorPresetEnum: string
{
    use HasMeta;

    /** The brand navy defined in index.css — the product default. */
    #[Label('Classic blue')]
    #[IsDefault(true)]
    case Default = 'default';

    #[Label('Forest green')]
    case Forest = 'forest';

    #[Label('Amber warm')]
    case Amber = 'amber';

    #[Label('Rose modern')]
    case Rose = 'rose';

    #[Label('Ocean breeze')]
    case Ocean = 'ocean';

    #[Label('Plum night')]
    case Plum = 'plum';

    #[Label('Graphite mono')]
    case Graphite = 'graphite';

    #[Label('Terracotta sun')]
    case Terracotta = 'terracotta';

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
