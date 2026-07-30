<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts;

/**
 * Validates a `products_table` block (spec 0069, config_schema #4): the
 * dynamic table of a record's product lines. Split out of
 * DocumentLayoutBlockValidator to keep that file under the size soft limit
 * (engineering.md §6). Two rules carry the module's D-4/D-11 decisions:
 *  - `columns[].lines[].keys` is checked against a CLOSED 9-value allow-list
 *    that deliberately has no `discount` entry (D-4, AC-031) — there is no
 *    source column for it anywhere in the schema;
 *  - `totals.rows[].variable` must be one of $totalsTokens, supplied by the
 *    caller (DocumentLayoutConfigValidator, sourced from
 *    DocumentLayoutVariableCatalog::totalsVariableTokens() — never
 *    duplicated here, per the module's own instruction).
 */
final class DocumentLayoutProductsTableBlockValidator
{
    /** @var array<int, string> */
    private const array PRODUCTS_TABLE_KEYS = ['id', 'type', 'source', 'width_pct', 'borders', 'show_header', 'header_background', 'columns', 'totals', 'empty_text'];

    /** @var array<int, string> */
    private const array BORDERS_KEYS = ['size', 'color'];

    /** @var array<int, string> */
    private const array SOURCE_VALUES = ['offer_lines', 'cost_lines'];

    /** @var array<int, string> */
    private const array COLUMN_KEYS = ['lines', 'label', 'width_pct', 'align'];

    /** @var array<int, string> */
    private const array COLUMN_ALIGN_VALUES = ['left', 'center', 'right'];

    /** @var array<int, string> */
    private const array LINE_KEYS = ['keys', 'separator', 'bold', 'italic', 'size'];

    /**
     * The ColumnKey allow-list (data_contract, config_schema #4) — every
     * key has a verified quote_lines/product source. `discount` is
     * deliberately absent (D-4).
     *
     * @var array<int, string>
     */
    private const array COLUMN_KEY_VALUES = [
        'code', 'name', 'description', 'quantity', 'unit_price', 'vat_rate', 'net_amount', 'vat_amount', 'total_amount',
    ];

    /** @var array<int, string> */
    private const array TOTALS_KEYS = ['show', 'rows'];

    /** @var array<int, string> */
    private const array TOTALS_ROW_KEYS = ['label', 'variable', 'bold'];

    /**
     * @param  array<string, mixed>  $block
     * @param  array<int, string>  $totalsTokens
     */
    public function validate(string $path, array $block, array $totalsTokens, DocumentLayoutConfigErrorBag $errors): void
    {
        ConfigShapeAssertions::assertKnownKeys($path, $block, self::PRODUCTS_TABLE_KEYS, $errors);
        ConfigShapeAssertions::assertEnum("{$path}.source", $block['source'] ?? null, self::SOURCE_VALUES, $errors);
        ConfigShapeAssertions::assertIntInRange("{$path}.width_pct", $block['width_pct'] ?? null, 1, 100, $errors);
        $this->assertBorders("{$path}.borders", $block['borders'] ?? null, $errors);
        ConfigShapeAssertions::assertBool("{$path}.show_header", $block['show_header'] ?? null, $errors);
        ConfigShapeAssertions::assertNullableHexColor("{$path}.header_background", $block['header_background'] ?? null, $errors);
        ConfigShapeAssertions::assertNullableString("{$path}.empty_text", $block['empty_text'] ?? null, $errors);

        $this->assertColumns("{$path}.columns", $block['columns'] ?? null, $errors);
        $this->assertTotals("{$path}.totals", $block['totals'] ?? null, $totalsTokens, $errors);
    }

    private function assertBorders(string $path, mixed $borders, DocumentLayoutConfigErrorBag $errors): void
    {
        if ($borders === null) {
            return;
        }

        if (! is_array($borders)) {
            $errors->add($path, 'Must be null or an object.');

            return;
        }

        ConfigShapeAssertions::assertKnownKeys($path, $borders, self::BORDERS_KEYS, $errors);
        ConfigShapeAssertions::assertIntInRange("{$path}.size", $borders['size'] ?? null, 1, 96, $errors);
        ConfigShapeAssertions::assertHexColor("{$path}.color", $borders['color'] ?? null, $errors);
    }

    private function assertColumns(string $path, mixed $columns, DocumentLayoutConfigErrorBag $errors): void
    {
        if (! is_array($columns) || ! array_is_list($columns) || $columns === []) {
            $errors->add($path, 'Must be a non-empty array of columns.');

            return;
        }

        if (count($columns) > DocumentLayoutConfigLimits::MAX_PRODUCT_COLUMNS) {
            $errors->add($path, 'Too many columns (max '.DocumentLayoutConfigLimits::MAX_PRODUCT_COLUMNS.').');
        }

        foreach ($columns as $index => $column) {
            $this->assertColumn("{$path}.{$index}", $column, $errors);
        }
    }

    private function assertColumn(string $path, mixed $column, DocumentLayoutConfigErrorBag $errors): void
    {
        if (! is_array($column)) {
            $errors->add($path, 'A column must be an object.');

            return;
        }

        ConfigShapeAssertions::assertKnownKeys($path, $column, self::COLUMN_KEYS, $errors);
        ConfigShapeAssertions::assertNonEmptyString("{$path}.label", $column['label'] ?? null, $errors);
        ConfigShapeAssertions::assertIntInRange("{$path}.width_pct", $column['width_pct'] ?? null, 1, 100, $errors);
        ConfigShapeAssertions::assertEnum("{$path}.align", $column['align'] ?? null, self::COLUMN_ALIGN_VALUES, $errors);

        $this->assertLines("{$path}.lines", $column['lines'] ?? null, $errors);
    }

    private function assertLines(string $path, mixed $lines, DocumentLayoutConfigErrorBag $errors): void
    {
        if (! is_array($lines) || ! array_is_list($lines) || $lines === []) {
            $errors->add($path, 'Must be a non-empty array of lines.');

            return;
        }

        if (count($lines) > DocumentLayoutConfigLimits::MAX_LINES_PER_PRODUCT_COLUMN) {
            $errors->add($path, 'Too many lines (max '.DocumentLayoutConfigLimits::MAX_LINES_PER_PRODUCT_COLUMN.').');
        }

        foreach ($lines as $index => $line) {
            $this->assertLine("{$path}.{$index}", $line, $errors);
        }
    }

    private function assertLine(string $path, mixed $line, DocumentLayoutConfigErrorBag $errors): void
    {
        if (! is_array($line)) {
            $errors->add($path, 'A line must be an object.');

            return;
        }

        ConfigShapeAssertions::assertKnownKeys($path, $line, self::LINE_KEYS, $errors);
        ConfigShapeAssertions::assertNullableString("{$path}.separator", $line['separator'] ?? null, $errors);
        ConfigShapeAssertions::assertBool("{$path}.bold", $line['bold'] ?? null, $errors);
        ConfigShapeAssertions::assertBool("{$path}.italic", $line['italic'] ?? null, $errors);
        ConfigShapeAssertions::assertNullableIntInRange("{$path}.size", $line['size'] ?? null, DocumentLayoutConfigLimits::FONT_SIZE_MIN, DocumentLayoutConfigLimits::FONT_SIZE_MAX, $errors);

        $this->assertKeys("{$path}.keys", $line['keys'] ?? null, $errors);
    }

    private function assertKeys(string $path, mixed $keys, DocumentLayoutConfigErrorBag $errors): void
    {
        if (! is_array($keys) || ! array_is_list($keys) || $keys === []) {
            $errors->add($path, 'Must be a non-empty array of column keys.');

            return;
        }

        if (count($keys) > DocumentLayoutConfigLimits::MAX_KEYS_PER_LINE) {
            $errors->add($path, 'Too many keys (max '.DocumentLayoutConfigLimits::MAX_KEYS_PER_LINE.').');
        }

        foreach ($keys as $index => $key) {
            if (! in_array($key, self::COLUMN_KEY_VALUES, true)) {
                $errors->add("{$path}.{$index}", 'Unknown column key.');
            }
        }
    }

    /**
     * @param  array<int, string>  $totalsTokens
     */
    private function assertTotals(string $path, mixed $totals, array $totalsTokens, DocumentLayoutConfigErrorBag $errors): void
    {
        if (! is_array($totals)) {
            $errors->add($path, 'The totals block is required.');

            return;
        }

        ConfigShapeAssertions::assertKnownKeys($path, $totals, self::TOTALS_KEYS, $errors);
        ConfigShapeAssertions::assertBool("{$path}.show", $totals['show'] ?? null, $errors);

        $rows = $totals['rows'] ?? null;

        if (! is_array($rows) || ! array_is_list($rows)) {
            $errors->add("{$path}.rows", 'Must be an array of totals rows.');

            return;
        }

        if (count($rows) > DocumentLayoutConfigLimits::MAX_TOTALS_ROWS) {
            $errors->add("{$path}.rows", 'Too many totals rows (max '.DocumentLayoutConfigLimits::MAX_TOTALS_ROWS.').');
        }

        foreach ($rows as $index => $row) {
            $this->assertTotalsRow("{$path}.rows.{$index}", $row, $totalsTokens, $errors);
        }
    }

    /**
     * @param  array<int, string>  $totalsTokens
     */
    private function assertTotalsRow(string $path, mixed $row, array $totalsTokens, DocumentLayoutConfigErrorBag $errors): void
    {
        if (! is_array($row)) {
            $errors->add($path, 'A totals row must be an object.');

            return;
        }

        ConfigShapeAssertions::assertKnownKeys($path, $row, self::TOTALS_ROW_KEYS, $errors);
        ConfigShapeAssertions::assertNonEmptyString("{$path}.label", $row['label'] ?? null, $errors);
        ConfigShapeAssertions::assertBool("{$path}.bold", $row['bold'] ?? null, $errors);

        if (! in_array($row['variable'] ?? null, $totalsTokens, true)) {
            $errors->add("{$path}.variable", 'Must be a variable of the "totals" category.');
        }
    }
}
