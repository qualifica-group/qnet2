<?php

declare(strict_types=1);

namespace App\CustomFields\Types;

/**
 * The `config.display` values of an `enum` field (custom field or attribute)
 * that store a LIST of option values instead of one (spec 0424 D-1): the
 * multiselect dropdown and the checkbox list differ only in the control.
 */
final class EnumDisplay
{
    public const string MULTISELECT = 'multiselect';

    public const string CHECKLIST = 'checklist';

    private const array MULTI_VALUE = [self::MULTISELECT, self::CHECKLIST];

    /**
     * @param  array<string, mixed>|null  $config
     */
    public static function isMultiValue(?array $config): bool
    {
        return in_array($config['display'] ?? null, self::MULTI_VALUE, true);
    }
}
