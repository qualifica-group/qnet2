<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts\Rendering\Blocks;

use App\Services\DocumentLayouts\Rendering\RenderContext;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Cell as CellElement;
use PhpOffice\PhpWord\Element\Table as TableElement;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\Style\Table as TableStyle;

/**
 * Renders a `products_table` block (spec 0069 config_schema #4 / spec 0070
 * rendering_contract, the "cuore della feature"):
 *  1. `source` selects the subject's rows (DocumentRenderSubject::productRows(),
 *     already ordered and formatted per column key — never re-sorted here);
 *  2. `show_header` -> one row per column with its `label`, `header_background`
 *     shading, marked `tblHeader` so it repeats on every page (AC-246);
 *  3. one row per subject row; each cell holds one PARAGRAPH per `lines[]`
 *     entry, its `keys` concatenated by `separator` (0069 D-11, AC-242);
 *     an entry resolving to empty text is skipped unless the whole cell is;
 *  4. no lines at all -> a single row, `empty_text` spanning every column
 *     via `gridSpan` (AC-243) — never an empty products row;
 *  5. `totals.show` -> one row per `totals.rows[]`: blank cells for every
 *     column except the label in the SECOND-TO-LAST cell and the resolved
 *     `variable` in the LAST, both bold when the row is (AC-244).
 */
final class ProductsTableRenderer
{
    /**
     * @param  array<string, mixed>  $block
     */
    public function render(AbstractContainer $container, array $block, RenderContext $context): void
    {
        $rows = $context->subject->productRows((string) $block['source']);
        $columns = $block['columns'];
        $columnWidthsTwips = array_map(
            static fn (array $column): int => $context->percentToTwips((int) $column['width_pct']),
            $columns,
        );
        $tableWidthTwips = $context->percentToTwips((int) $block['width_pct']);

        $table = $container->addTable($this->tableStyle($block, $tableWidthTwips));

        if ($block['show_header']) {
            $this->renderHeaderRow($table, $columns, $columnWidthsTwips, (string) ($block['header_background'] ?? ''));
        }

        if ($rows === []) {
            $this->renderEmptyRow($table, $tableWidthTwips, count($columns), (string) $block['empty_text']);
        } else {
            foreach ($rows as $productRow) {
                $this->renderProductRow($table, $columns, $columnWidthsTwips, $productRow);
            }
        }

        if ($block['totals']['show'] ?? false) {
            foreach ($block['totals']['rows'] as $totalsRow) {
                $this->renderTotalsRow($table, $columnWidthsTwips, $totalsRow, $context);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @param  array<int, int>  $columnWidthsTwips
     */
    private function renderHeaderRow(TableElement $table, array $columns, array $columnWidthsTwips, string $headerBackground): void
    {
        $row = $table->addRow(null, ['tblHeader' => true]);

        foreach ($columns as $index => $column) {
            $cell = $row->addCell($columnWidthsTwips[$index], ['bgColor' => $headerBackground !== '' ? $headerBackground : null]);
            $cell->addText((string) $column['label'], ['bold' => true], ['align' => $column['align']]);
        }
    }

    private function renderEmptyRow(TableElement $table, int $tableWidthTwips, int $columnCount, string $emptyText): void
    {
        $row = $table->addRow();
        $cell = $row->addCell($tableWidthTwips, ['gridSpan' => $columnCount]);
        $cell->addText($emptyText);
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @param  array<int, int>  $columnWidthsTwips
     * @param  array<string, string>  $productRow
     */
    private function renderProductRow(TableElement $table, array $columns, array $columnWidthsTwips, array $productRow): void
    {
        $row = $table->addRow();

        foreach ($columns as $index => $column) {
            $cell = $row->addCell($columnWidthsTwips[$index], ['valign' => 'center']);
            $this->renderColumnLines($cell, $column, $productRow);
        }
    }

    /**
     * @param  array<string, mixed>  $column
     * @param  array<string, string>  $productRow
     */
    private function renderColumnLines(CellElement $cell, array $column, array $productRow): void
    {
        $texts = array_map(
            fn (array $columnLine): string => implode(
                (string) ($columnLine['separator'] ?? ''),
                array_map(static fn (string $key): string => $productRow[$key] ?? '', $columnLine['keys']),
            ),
            $column['lines'],
        );
        // An optional value (e.g. a line without additional_description) must
        // not print a blank paragraph under the others; a cell whose lines are
        // ALL empty still keeps them, since a Word cell needs a paragraph.
        $keepEmpty = implode('', $texts) === '';

        foreach ($column['lines'] as $index => $columnLine) {
            $text = $texts[$index];

            if ($text === '' && ! $keepEmpty) {
                continue;
            }

            $cell->addText($text, [
                'bold' => (bool) ($columnLine['bold'] ?? false),
                'italic' => (bool) ($columnLine['italic'] ?? false),
                'size' => $columnLine['size'] ?? null,
            ], ['align' => $column['align']]);
        }
    }

    /**
     * @param  array<int, int>  $columnWidthsTwips
     * @param  array<string, mixed>  $totalsRow
     */
    private function renderTotalsRow(TableElement $table, array $columnWidthsTwips, array $totalsRow, RenderContext $context): void
    {
        $row = $table->addRow();
        $columnCount = count($columnWidthsTwips);
        $bold = (bool) ($totalsRow['bold'] ?? false);

        for ($index = 0; $index < $columnCount - 2; $index++) {
            $row->addCell($columnWidthsTwips[$index]);
        }

        if ($columnCount >= 2) {
            $row->addCell($columnWidthsTwips[$columnCount - 2])->addText((string) $totalsRow['label'], ['bold' => $bold]);
        }

        $value = $context->substitute((string) $totalsRow['variable']);
        $row->addCell($columnWidthsTwips[$columnCount - 1])->addText($value, ['bold' => $bold]);
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function tableStyle(array $block, int $tableWidthTwips): array
    {
        $style = [
            'width' => $tableWidthTwips,
            'unit' => TblWidth::TWIP,
            'layout' => TableStyle::LAYOUT_FIXED,
        ];

        $borders = $block['borders'] ?? null;

        if ($borders !== null) {
            $style['borderSize'] = $borders['size'];
            $style['borderColor'] = $borders['color'];
        }

        return $style;
    }
}
