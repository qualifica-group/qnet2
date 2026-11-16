<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * The detail card of one `table` row (spec 0390): column label => value as a
 * reader expects it, in the shape RecordMail prints. Dates in the d/m/Y form
 * RecordDetails already uses, yes/no as translatable `notifications.values`
 * keys (rendered in the recipient's locale), an option by its label. Empty
 * cells are omitted, like every other detail card.
 */
final class TableRowDetails
{
    private const string DATE_FORMAT = 'd/m/Y';

    private const string DATETIME_FORMAT = 'd/m/Y H:i';

    /**
     * @param  array<string, mixed>  $config  the definition's table config
     * @param  array<string, mixed>  $row  one stored row
     * @return array<string, string>
     */
    public static function for(array $config, array $row): array
    {
        $details = [];

        foreach ($config['columns'] ?? [] as $column) {
            if (! is_array($column) || ! isset($column['key'], $column['label'])) {
                continue;
            }

            $value = self::format($column, $row[$column['key']] ?? null);

            if ($value !== null) {
                $details[(string) $column['label']] = $value;
            }
        }

        $selectable = $config['selectable'] ?? null;

        if (is_array($selectable) && isset($selectable['key'], $selectable['label'])) {
            $details[(string) $selectable['label']] = self::yesNo($row[$selectable['key']] ?? false);
        }

        return $details;
    }

    /**
     * The label of the column (or of the row selection) a trigger key names.
     *
     * @param  array<string, mixed>  $config
     */
    public static function columnLabel(array $config, string $key): string
    {
        $selectable = $config['selectable'] ?? null;

        if (is_array($selectable) && ($selectable['key'] ?? null) === $key) {
            return (string) ($selectable['label'] ?? $key);
        }

        $column = collect($config['columns'] ?? [])->first(fn (mixed $column): bool => is_array($column) && ($column['key'] ?? null) === $key);

        return (string) ($column['label'] ?? $key);
    }

    /**
     * @param  array<string, mixed>  $column
     */
    private static function format(array $column, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($column['type'] ?? null) {
            'boolean' => self::yesNo($value),
            'date' => self::date($value, self::DATE_FORMAT),
            'datetime' => self::date($value, self::DATETIME_FORMAT),
            'enum' => self::optionLabel($column['options'] ?? [], $value),
            default => is_scalar($value) ? (string) $value : null,
        };
    }

    private static function yesNo(mixed $value): string
    {
        return (bool) $value ? 'notifications.values.yes' : 'notifications.values.no';
    }

    private static function date(mixed $value, string $format): string
    {
        try {
            return CarbonImmutable::parse((string) $value)->format($format);
        } catch (Throwable) {
            return (string) $value;
        }
    }

    /**
     * @param  array<int, mixed>  $options
     */
    private static function optionLabel(array $options, mixed $value): string
    {
        $option = collect($options)->first(fn (mixed $option): bool => is_array($option) && (string) ($option['value'] ?? '') === (string) $value);

        return (string) ($option['label'] ?? $value);
    }
}
