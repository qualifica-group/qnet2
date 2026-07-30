<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts\Rendering\Blocks;

use App\Services\DocumentLayouts\Rendering\RenderContext;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Row as RowElement;
use PhpOffice\PhpWord\Element\Table as TableElement;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\Style\Table as TableStyle;

/**
 * Renders a static `table` block (spec 0069 config_schema #3 / spec 0070
 * rendering_contract): every `width_pct` (the table's own and each column's)
 * converts to twips against the SAME usable-width base
 * (`RenderContext::percentToTwips()` — "calcolata una volta e riusata", the
 * single conversion point the contract mandates). A cell's width is the sum
 * of the column widths it spans (`col_span`), matched POSITIONALLY: the
 * schema declares widths on `columns[]`, not on the cells themselves. Each
 * cell's `blocks` is a list of `text` blocks (spec 0069's deliberate "una
 * cella contiene SOLO blocchi text"), rendered by TextBlockRenderer exactly
 * like a zone's own top-level blocks.
 */
final class TableBlockRenderer
{
    public function __construct(private readonly TextBlockRenderer $textBlockRenderer) {}

    /**
     * @param  array<string, mixed>  $block
     */
    public function render(AbstractContainer $container, array $block, RenderContext $context): void
    {
        $tableWidthTwips = $context->percentToTwips((int) $block['width_pct']);
        $columnWidthsTwips = array_map(
            static fn (array $column): int => $context->percentToTwips((int) $column['width_pct']),
            $block['columns'],
        );

        $table = $container->addTable($this->tableStyle($block, $tableWidthTwips));

        foreach ($block['rows'] as $row) {
            $this->renderRow($table, $row, $columnWidthsTwips, $context);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, int>  $columnWidthsTwips
     */
    private function renderRow(TableElement $table, array $row, array $columnWidthsTwips, RenderContext $context): void
    {
        $tableRow = $table->addRow(null, ['tblHeader' => (bool) ($row['is_header'] ?? false)]);
        $columnIndex = 0;

        foreach ($row['cells'] as $cell) {
            $colSpan = (int) ($cell['col_span'] ?? 1);
            $widthTwips = array_sum(array_slice($columnWidthsTwips, $columnIndex, $colSpan));
            $columnIndex += $colSpan;

            $this->renderCell($tableRow, $cell, $widthTwips, $context);
        }
    }

    /**
     * @param  array<string, mixed>  $cell
     */
    private function renderCell(RowElement $tableRow, array $cell, int $widthTwips, RenderContext $context): void
    {
        $cellElement = $tableRow->addCell($widthTwips, [
            'gridSpan' => $cell['col_span'] ?? 1,
            'bgColor' => $cell['background'] ?? null,
            'valign' => $cell['vertical_align'] ?? 'top',
        ]);

        foreach ($cell['blocks'] as $textBlock) {
            $this->textBlockRenderer->render($cellElement, $textBlock, $context);
        }
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
