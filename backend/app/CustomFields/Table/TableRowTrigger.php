<?php

declare(strict_types=1);

namespace App\CustomFields\Table;

/**
 * Which rows of a `table` value fire the field's row event (spec 0390, D-2),
 * comparing the stored value before a write with the normalized one after it.
 * Pure: no database, no notification.
 *
 * Without a trigger column every row whose id was not stored before fires (a
 * row was added). With one, a row fires when it is saved with that yes/no
 * value on and it was not on before (a new row, or an existing one switched
 * on): saving the same rows again never fires twice.
 */
final class TableRowTrigger
{
    /**
     * @param  array<string, mixed>  $config  the definition's table config
     * @return array<int, array<string, mixed>> the rows that fire, as stored after the write
     */
    public static function firedRows(array $config, mixed $before, mixed $after): array
    {
        $notify = $config['notify'] ?? null;
        $afterRows = self::rows($after);

        if (! is_array($notify) || $afterRows === []) {
            return [];
        }

        $trigger = is_string($notify['trigger'] ?? null) ? $notify['trigger'] : null;
        $beforeRows = collect(self::rows($before))
            ->filter(fn (array $row): bool => is_string($row['id'] ?? null))
            ->keyBy('id')
            ->all();

        return array_values(array_filter(
            $afterRows,
            fn (array $row): bool => self::fires($row, $beforeRows[$row['id'] ?? ''] ?? null, $trigger),
        ));
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>|null  $previous  the same row before the write, null when new
     */
    private static function fires(array $row, ?array $previous, ?string $trigger): bool
    {
        if ($trigger === null) {
            return $previous === null;
        }

        return self::isOn($row[$trigger] ?? null) && ! self::isOn($previous[$trigger] ?? null);
    }

    /** Stored booleans are read back the way BooleanFieldType reads them. */
    private static function isOn(mixed $value): bool
    {
        return $value !== null && (bool) $value;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function rows(mixed $value): array
    {
        $rows = is_array($value) && is_array($value['rows'] ?? null) ? $value['rows'] : [];

        return array_values(array_filter($rows, 'is_array'));
    }
}
