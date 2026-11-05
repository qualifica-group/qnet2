<?php

declare(strict_types=1);

namespace App\Exports\ChartOfAccounts;

use Illuminate\Support\Str;

/**
 * The fixed file layout shared by the chart-of-accounts export and import
 * (spec 0349, D-3): column order, and the header/value vocabulary in both
 * languages. Files are written in the user's language and read in either.
 */
final class ChartOfAccountsLayout
{
    public const string LEVEL_GROUP = 'group';

    public const string LEVEL_ACCOUNT = 'account';

    public const string LEVEL_SUB_ACCOUNT = 'sub_account';

    /** Columns in file order. */
    public const array COLUMNS = ['level', 'code', 'parent_code', 'name', 'description', 'kind', 'nature', 'color', 'active'];

    /** Vocabulary groups translated under `chart_accounts.io.*`. */
    public const string LEVELS = 'levels';

    public const string KINDS = 'kinds';

    public const string NATURES = 'natures';

    private const array LOCALES = ['it', 'en'];

    /** Language-independent spellings accepted for the Active column. */
    private const array TRUE_ALIASES = ['1', 'true'];

    private const array FALSE_ALIASES = ['0', 'false'];

    public static function header(string $column): string
    {
        return __("chart_accounts.io.headers.{$column}");
    }

    public static function value(string $group, string $canonical): string
    {
        return __("chart_accounts.io.{$group}.{$canonical}");
    }

    public static function booleanLabel(bool $value): string
    {
        return __('chart_accounts.io.booleans.'.($value ? 'yes' : 'no'));
    }

    /**
     * Normalized header text (any language) => column key.
     *
     * @return array<string, string>
     */
    public static function headerLookup(): array
    {
        $lookup = [];

        foreach (self::COLUMNS as $column) {
            foreach (self::LOCALES as $locale) {
                $lookup[self::normalize(trans("chart_accounts.io.headers.{$column}", [], $locale))] = $column;
            }
        }

        return $lookup;
    }

    /**
     * Canonical value of a vocabulary cell in either language, or null.
     */
    public static function parseValue(string $group, string $raw): ?string
    {
        $needle = self::normalize($raw);

        foreach (array_keys((array) trans("chart_accounts.io.{$group}", [], 'en')) as $canonical) {
            if ($needle === self::normalize((string) $canonical)) {
                return (string) $canonical;
            }

            foreach (self::LOCALES as $locale) {
                if ($needle === self::normalize(trans("chart_accounts.io.{$group}.{$canonical}", [], $locale))) {
                    return (string) $canonical;
                }
            }
        }

        return null;
    }

    public static function parseBoolean(string $raw): ?bool
    {
        $needle = self::normalize($raw);
        $yes = [...self::TRUE_ALIASES];
        $no = [...self::FALSE_ALIASES];

        foreach (self::LOCALES as $locale) {
            $yes[] = self::normalize(trans('chart_accounts.io.booleans.yes', [], $locale));
            $no[] = self::normalize(trans('chart_accounts.io.booleans.no', [], $locale));
        }

        return match (true) {
            in_array($needle, $yes, true) => true,
            in_array($needle, $no, true) => false,
            default => null,
        };
    }

    /**
     * Case, accent, space and punctuation insensitive form (`Sì` = `si`).
     */
    public static function normalize(string $text): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($text)));
    }
}
