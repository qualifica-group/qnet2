<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts\Rendering\Blocks;

use App\Models\QuoteLine;
use App\Services\DocumentLayouts\Rendering\RenderContext;
use App\Services\DocumentLayouts\Rendering\VariableResolver;
use Illuminate\Support\Collection;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Cell as CellElement;
use PhpOffice\PhpWord\Element\Table as TableElement;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\Style\Table as TableStyle;

/**
 * Renders a `products_table` block (spec 0069 config_schema #4 / spec 0070
 * rendering_contract, the "cuore della feature"):
 *  1. `source` selects `offerLines`/`costLines` (already sort_order-ordered
 *     relations on Quote — never re-queried/re-sorted here);
 *  2. `show_header` -> one row per column with its `label`, `header_background`
 *     shading, marked `tblHeader` so it repeats on every page (AC-246);
 *  3. one row per QuoteLine; each cell holds one PARAGRAPH per `lines[]`
 *     entry, its `keys` concatenated by `separator` (0069 D-11, AC-242);
 *  4. no lines at all -> a single row, `empty_text` spanning every column
 *     via `gridSpan` (AC-243) — never an empty products row;
 *  5. `totals.show` -> one row per `totals.rows[]`: blank cells for every
 *     column except the label in the SECOND-TO-LAST cell and the resolved
 *     `variable` in the LAST, both bold when the row is (AC-244).
 */
final class ProductsTableRenderer
{
    private const string SOURCE_COST_LINES = 'cost_lines';

    public function __construct(
        private readonly ProductLineColumnResolver $columnResolver,
        private readonly VariableResolver $variableResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $block
     */
    public function render(AbstractContainer $container, array $block, RenderContext $context): void
    {
        $lines = $this->sourceLines($block, $context);
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

        if ($lines->isEmpty()) {
            $this->renderEmptyRow($table, $tableWidthTwips, count($columns), (string) $block['empty_text']);
        } else {
            foreach ($lines as $line) {
                $this->renderLineRow($table, $columns, $columnWidthsTwips, $line);
            }
        }

        if ($block['totals']['show'] ?? false) {
            foreach ($block['totals']['rows'] as $totalsRow) {
                $this->renderTotalsRow($table, $columnWidthsTwips, $totalsRow, $context);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $block
     * @return Collection<int, QuoteLine>
     */
    private function sourceLines(array $block, RenderContext $context): Collection
    {
        return $block['source'] === self::SOURCE_COST_LINES
            ? $context->quote->costLines
            : $context->quote->offerLines;
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
     */
    private function renderLineRow(TableElement $table, array $columns, array $columnWidthsTwips, QuoteLine $line): void
    {
        $row = $table->addRow();

        foreach ($columns as $index => $column) {
            $cell = $row->addCell($columnWidthsTwips[$index], ['valign' => 'center']);
            $this->renderColumnLines($cell, $column, $line);
        }
    }

    /**
     * @param  array<string, mixed>  $column
     */
    private function renderColumnLines(CellElement $cell, array $column, QuoteLine $line): void
    {
        foreach ($column['lines'] as $columnLine) {
            $text = implode(
                (string) ($columnLine['separator'] ?? ''),
                array_map(fn (string $key): string => $this->columnResolver->value($key, $line), $columnLine['keys']),
            );

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

        $value = $this->variableResolver->substitute((string) $totalsRow['variable'], $context->quote, $context->actor);
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
