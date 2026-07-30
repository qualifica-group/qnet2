<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts;

/**
 * Validates a `table` block (spec 0069, config_schema #3): a static table
 * whose cells may ONLY contain `text` blocks (D "vincolo deliberato: nessuna
 * tabella in tabella, nessuna immagine in cella" — AC-034). Split out of
 * DocumentLayoutBlockValidator to keep that file under the size soft limit
 * (engineering.md §6); text-block validation itself is NOT duplicated here —
 * every cell's blocks are delegated back to the injected
 * DocumentLayoutBlockValidator::validateTextBlock().
 */
final class DocumentLayoutTableBlockValidator
{
    /** @var array<int, string> */
    private const array TABLE_KEYS = ['id', 'type', 'width_pct', 'borders', 'columns', 'rows'];

    /** @var array<int, string> */
    private const array BORDERS_KEYS = ['size', 'color'];

    /** @var array<int, string> */
    private const array COLUMN_KEYS = ['width_pct'];

    /** @var array<int, string> */
    private const array ROW_KEYS = ['is_header', 'cells'];

    /** @var array<int, string> */
    private const array CELL_KEYS = ['col_span', 'background', 'vertical_align', 'blocks'];

    /** @var array<int, string> */
    private const array VERTICAL_ALIGN_VALUES = ['top', 'center', 'bottom'];

    /**
     * @param  array<string, mixed>  $block
     */
    public function validate(string $path, array $block, DocumentLayoutBlockValidator $textBlockValidator, DocumentLayoutConfigErrorBag $errors): void
    {
        ConfigShapeAssertions::assertKnownKeys($path, $block, self::TABLE_KEYS, $errors);
        ConfigShapeAssertions::assertIntInRange("{$path}.width_pct", $block['width_pct'] ?? null, 1, 100, $errors);

        $this->assertBorders("{$path}.borders", $block['borders'] ?? null, $errors);
        $this->assertColumns("{$path}.columns", $block['columns'] ?? null, $errors);
        $this->assertRows("{$path}.rows", $block['rows'] ?? null, $textBlockValidator, $errors);
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

        if (count($columns) > DocumentLayoutConfigLimits::MAX_TABLE_COLUMNS) {
            $errors->add($path, 'Too many columns (max '.DocumentLayoutConfigLimits::MAX_TABLE_COLUMNS.').');
        }

        foreach ($columns as $index => $column) {
            $columnPath = "{$path}.{$index}";

            if (! is_array($column)) {
                $errors->add($columnPath, 'A column must be an object.');

                continue;
            }

            ConfigShapeAssertions::assertKnownKeys($columnPath, $column, self::COLUMN_KEYS, $errors);
            ConfigShapeAssertions::assertIntInRange("{$columnPath}.width_pct", $column['width_pct'] ?? null, 1, 100, $errors);
        }
    }

    private function assertRows(string $path, mixed $rows, DocumentLayoutBlockValidator $textBlockValidator, DocumentLayoutConfigErrorBag $errors): void
    {
        if (! is_array($rows) || ! array_is_list($rows)) {
            $errors->add($path, 'Must be an array of rows.');

            return;
        }

        if (count($rows) > DocumentLayoutConfigLimits::MAX_TABLE_ROWS) {
            $errors->add($path, 'Too many rows (max '.DocumentLayoutConfigLimits::MAX_TABLE_ROWS.').');
        }

        foreach ($rows as $index => $row) {
            $this->assertRow("{$path}.{$index}", $row, $textBlockValidator, $errors);
        }
    }

    private function assertRow(string $path, mixed $row, DocumentLayoutBlockValidator $textBlockValidator, DocumentLayoutConfigErrorBag $errors): void
    {
        if (! is_array($row)) {
            $errors->add($path, 'A row must be an object.');

            return;
        }

        ConfigShapeAssertions::assertKnownKeys($path, $row, self::ROW_KEYS, $errors);
        ConfigShapeAssertions::assertBool("{$path}.is_header", $row['is_header'] ?? null, $errors);

        $cells = $row['cells'] ?? null;

        if (! is_array($cells) || ! array_is_list($cells) || $cells === []) {
            $errors->add("{$path}.cells", 'Must be a non-empty array of cells.');

            return;
        }

        foreach ($cells as $index => $cell) {
            $this->assertCell("{$path}.cells.{$index}", $cell, $textBlockValidator, $errors);
        }
    }

    private function assertCell(string $path, mixed $cell, DocumentLayoutBlockValidator $textBlockValidator, DocumentLayoutConfigErrorBag $errors): void
    {
        if (! is_array($cell)) {
            $errors->add($path, 'A cell must be an object.');

            return;
        }

        ConfigShapeAssertions::assertKnownKeys($path, $cell, self::CELL_KEYS, $errors);
        ConfigShapeAssertions::assertIntInRange("{$path}.col_span", $cell['col_span'] ?? null, 1, DocumentLayoutConfigLimits::MAX_TABLE_COLUMNS, $errors);
        ConfigShapeAssertions::assertNullableHexColor("{$path}.background", $cell['background'] ?? null, $errors);
        ConfigShapeAssertions::assertEnum("{$path}.vertical_align", $cell['vertical_align'] ?? null, self::VERTICAL_ALIGN_VALUES, $errors);

        $blocks = $cell['blocks'] ?? null;

        if (! is_array($blocks) || ! array_is_list($blocks)) {
            $errors->add("{$path}.blocks", 'Must be an array of text blocks.');

            return;
        }

        foreach ($blocks as $index => $cellBlock) {
            $this->assertCellBlock("{$path}.blocks.{$index}", $cellBlock, $textBlockValidator, $errors);
        }
    }

    private function assertCellBlock(string $path, mixed $cellBlock, DocumentLayoutBlockValidator $textBlockValidator, DocumentLayoutConfigErrorBag $errors): void
    {
        if (! is_array($cellBlock)) {
            $errors->add($path, 'A cell block must be an object.');

            return;
        }

        if (($cellBlock['type'] ?? null) !== 'text') {
            $errors->add("{$path}.type", 'A table cell may only contain "text" blocks.');

            return;
        }

        $textBlockValidator->validateTextBlock($path, $cellBlock, $errors);
    }
}
