<?php

namespace App\Support;

/**
 * Server-side allow-lists for the two badge attributes shared by every
 * lookup configurator that carries `color`/`icon` (spec 0101, D-4): the
 * badge COLOR is a palette TOKEN, never a hex value, and the badge ICON is a
 * lucide kebab-case name from the curated catalogue — never free text.
 *
 * Mirrors, VERBATIM, the two frontend sources of truth so a value validated
 * here always resolves to a swatch/glyph the grid can render:
 * `frontend/src/features/custom-fields/badge-color-tokens.ts` (`BADGE_COLOR_TOKENS`)
 * and `frontend/src/features/custom-fields/icon-catalog.ts` (`ICON_NAMES`).
 * Consumed by FormRequests via `Rule::in(BadgeTokens::colors())` /
 * `Rule::in(BadgeTokens::icons())`.
 */
class BadgeTokens
{
    /**
     * The 14 badge color tokens, in the exact order of `BADGE_COLOR_TOKENS`.
     *
     * @var array<int, string>
     */
    private const array COLOR_TOKENS = [
        'slate', 'gray', 'red', 'orange', 'amber', 'yellow', 'green',
        'emerald', 'teal', 'blue', 'indigo', 'violet', 'purple', 'pink',
    ];

    /**
     * The curated lucide icon names, in the exact sorted order of
     * `ICON_NAMES` (`Object.keys(ICON_CATALOG).sort()`).
     *
     * @var array<int, string>
     */
    private const array ICON_NAMES = [
        'activity', 'alarm-clock', 'at-sign', 'award', 'badge-check',
        'bar-chart-3', 'bell', 'bike', 'book', 'book-open', 'bookmark',
        'box', 'briefcase', 'building', 'building-2', 'calculator',
        'calendar', 'calendar-days', 'camera', 'car', 'check-circle-2',
        'clipboard-list', 'clock', 'cloud', 'code', 'coffee', 'cog',
        'coins', 'compass', 'credit-card', 'database', 'dollar-sign',
        'download', 'droplet', 'euro', 'eye', 'factory', 'file',
        'file-text', 'files', 'filter', 'flag', 'flame', 'folder',
        'gauge', 'gem', 'gift', 'globe', 'graduation-cap', 'hammer',
        'hard-drive', 'hard-hat', 'hash', 'headphones', 'heart', 'home',
        'id-card', 'image', 'inbox', 'info', 'key', 'landmark', 'laptop',
        'layers', 'layout-grid', 'leaf', 'lightbulb', 'line-chart',
        'link', 'list', 'lock', 'mail', 'map', 'map-pin', 'medal',
        'message-circle', 'message-square', 'mic', 'monitor', 'moon',
        'music', 'navigation', 'package', 'paperclip', 'pen-line',
        'percent', 'phone', 'pie-chart', 'piggy-bank', 'plane', 'plug',
        'printer', 'puzzle', 'receipt', 'rocket', 'route', 'ruler',
        'send', 'server', 'settings', 'shield', 'shield-check',
        'shopping-bag', 'shopping-cart', 'signature', 'sliders-horizontal',
        'smartphone', 'smile', 'sparkles', 'star', 'store', 'sun', 'table',
        'tablet', 'tag', 'tags', 'target', 'thumbs-up', 'ticket', 'timer',
        'trophy', 'truck', 'type', 'user', 'users', 'utensils', 'video',
        'wallet', 'warehouse', 'waypoints', 'wifi', 'wine', 'wrench', 'zap',
    ];

    /**
     * @return array<int, string>
     */
    public static function colors(): array
    {
        return self::COLOR_TOKENS;
    }

    /**
     * @return array<int, string>
     */
    public static function icons(): array
    {
        return self::ICON_NAMES;
    }
}
