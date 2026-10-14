<?php

declare(strict_types=1);

namespace App\Tables\Support;

/**
 * Marks the listed catalogue columns as inline-editable (spec 0258, D-2):
 * each id maps to the extra keys its cell needs (`nullable`, `editor`,
 * `options`), merged over `'editable' => true`. Columns not listed are
 * returned untouched. Pure data, shared by the lookup catalogues.
 */
final class EditableColumns
{
    /** The grid's palette editor (spec 0258, D-3). */
    public const array COLOR = ['editor' => 'color'];

    public const array NULLABLE = ['nullable' => true];

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @param  array<string, array<string, mixed>>  $editable  column id => extra keys
     * @return array<int, array<string, mixed>>
     */
    public static function apply(array $columns, array $editable): array
    {
        return array_map(
            static fn (array $column): array => isset($editable[$column['id']])
                ? [...$column, 'editable' => true, ...$editable[$column['id']]]
                : $column,
            $columns,
        );
    }
}
