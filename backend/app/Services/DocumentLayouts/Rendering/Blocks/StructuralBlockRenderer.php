<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts\Rendering\Blocks;

use App\Services\DocumentLayouts\Rendering\RenderContext;
use App\Services\DocumentLayouts\Rendering\RenderingUnits;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Section;

/**
 * Renders the three "plain layout" block types (spec 0069 config_schema
 * #5/#6/#7 / spec 0070 rendering_contract) that need no variable resolution:
 *  - `page_break` -> `addPageBreak()`. PhpWord only allows this element
 *    inside a `Section` (verified against `AbstractContainer::checkValidity()`
 *    — `Header`/`Footer`/`Cell` are not in its allow-list), so a page_break
 *    placed in header/footer is a no-op here rather than a fatal
 *    BadMethodCallException: the config schema does not itself restrict the
 *    zone, but repeating a page break on every header/footer render would
 *    be meaningless anyway.
 *  - `spacer` -> a bare `addTextBreak()` whose paragraph carries a
 *    `spaceAfter` equal to the block's `height` (points) converted to twips.
 *  - `divider` -> a paragraph with a bottom border (`borderBottomSize` in
 *    eighths of a point, matching the config's own unit — no conversion) and
 *    no text; `width_pct` is rendered by pulling the right indentation in so
 *    the border only spans that fraction of the usable width, since a Word
 *    paragraph border has no independent "width" of its own.
 */
final class StructuralBlockRenderer
{
    public function pageBreak(AbstractContainer $container): void
    {
        if ($container instanceof Section) {
            $container->addPageBreak();
        }
    }

    /**
     * @param  array<string, mixed>  $block
     */
    public function spacer(AbstractContainer $container, array $block): void
    {
        $heightTwips = (int) $block['height'] * RenderingUnits::POINTS_TO_TWIPS;

        $container->addTextBreak(1, null, ['spaceAfter' => $heightTwips]);
    }

    /**
     * @param  array<string, mixed>  $block
     */
    public function divider(AbstractContainer $container, array $block, RenderContext $context): void
    {
        $widthTwips = $context->percentToTwips((int) $block['width_pct']);
        $indentRight = max(0, $context->usableWidthTwips - $widthTwips);

        $container->addTextRun([
            'borderBottomSize' => $block['thickness'],
            'borderBottomColor' => $block['color'],
            'indentRight' => $indentRight,
            'spaceBefore' => $block['space_before'],
            'spaceAfter' => $block['space_after'],
        ]);
    }
}
