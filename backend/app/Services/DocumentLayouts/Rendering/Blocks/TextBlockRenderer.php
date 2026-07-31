<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts\Rendering\Blocks;

use App\Services\DocumentLayouts\Rendering\RenderContext;
use App\Services\DocumentLayouts\Rendering\VariableResolver;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\SimpleType\LineSpacingRule;
use PhpOffice\PhpWord\Style\Font;
use PhpOffice\PhpWord\Style\Paragraph;

/**
 * Renders a `text` block (spec 0069 config_schema #1 / spec 0070
 * rendering_contract): one paragraph (`addTextRun`) with one `addText()` per
 * run, or one `addField('PAGE'|'NUMPAGES')` for a run whose `field` is set
 * (its `text` is then ignored, per contract). `runs: []` renders as a bare
 * `addTextBreak()` (deliberate empty-paragraph spacing), never an empty
 * TextRun.
 *
 * Also the renderer for a `table` cell's own blocks (spec 0069: "una cella
 * contiene SOLO blocchi text") — TableBlockRenderer calls `render()` once per
 * text block inside a cell, exactly like a zone's top-level blocks.
 */
final class TextBlockRenderer
{
    private const array FIELD_TYPES = ['page' => 'PAGE', 'total_pages' => 'NUMPAGES'];

    public function __construct(private readonly VariableResolver $variableResolver) {}

    /**
     * @param  array<string, mixed>  $block
     */
    public function render(AbstractContainer $container, array $block, RenderContext $context): void
    {
        $runs = $block['runs'] ?? [];

        if ($runs === []) {
            $container->addTextBreak();

            return;
        }

        $paragraphStyle = $this->paragraphStyle($block);
        $textRun = $container->addTextRun($paragraphStyle);

        foreach ($runs as $run) {
            $this->renderRun($textRun, $run, $context);
        }
    }

    /**
     * @param  array<string, mixed>  $run
     */
    private function renderRun(TextRun $textRun, array $run, RenderContext $context): void
    {
        $fontStyle = $this->fontStyle($run);
        $field = $run['field'] ?? null;

        if ($field !== null) {
            $textRun->addField(self::FIELD_TYPES[$field], [], [], null, $fontStyle);

            return;
        }

        $resolvedText = $this->variableResolver->substitute((string) ($run['text'] ?? ''), $context->quote, $context->actor);
        $textRun->addText($resolvedText, $fontStyle);
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function paragraphStyle(array $block): array
    {
        $style = ['align' => $block['align'] ?? 'left'];

        if (isset($block['space_before'])) {
            $style['spaceBefore'] = $block['space_before'];
        }

        if (isset($block['space_after'])) {
            $style['spaceAfter'] = $block['space_after'];
        }

        if (isset($block['line_height'])) {
            // NOT PhpWord's `lineHeight` shortcut: it stores (lineHeight - 1) *
            // 240 as a FLOAT and Writer\Style\Spacing prints that value verbatim
            // into `w:line` (line_height 1.08 -> w:line="259.20000000000005"),
            // while OOXML types the attribute as an integer twips measure. Same
            // computation, rounded to whole twips.
            $style['spacing'] = (int) round(((float) $block['line_height'] - 1) * Paragraph::LINE_HEIGHT);
            $style['spacingLineRule'] = LineSpacingRule::AUTO;
        }

        return $style;
    }

    /**
     * @param  array<string, mixed>  $run
     * @return array<string, mixed>
     */
    private function fontStyle(array $run): array
    {
        $style = [
            'bold' => (bool) ($run['bold'] ?? false),
            'italic' => (bool) ($run['italic'] ?? false),
            'underline' => ($run['underline'] ?? false) ? Font::UNDERLINE_SINGLE : Font::UNDERLINE_NONE,
        ];

        if (! empty($run['font'])) {
            $style['name'] = $run['font'];
        }

        if (! empty($run['size'])) {
            $style['size'] = $run['size'];
        }

        if (! empty($run['color'])) {
            $style['color'] = $run['color'];
        }

        return $style;
    }
}
