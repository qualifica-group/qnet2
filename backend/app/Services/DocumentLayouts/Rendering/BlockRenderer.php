<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts\Rendering;

use App\Services\DocumentLayouts\Rendering\Blocks\ImageBlockRenderer;
use App\Services\DocumentLayouts\Rendering\Blocks\ProductsTableRenderer;
use App\Services\DocumentLayouts\Rendering\Blocks\StructuralBlockRenderer;
use App\Services\DocumentLayouts\Rendering\Blocks\TableBlockRenderer;
use App\Services\DocumentLayouts\Rendering\Blocks\TextBlockRenderer;
use PhpOffice\PhpWord\Element\AbstractContainer;

/**
 * Dispatches one zone's `blocks` list (spec 0069 config_schema, spec 0070
 * rendering_contract) to the renderer for its `type` — the 7 closed block
 * types, one match arm each. Used by DocxRenderer for header/body/footer;
 * each per-family renderer stays its own class (engineering.md §6).
 */
final class BlockRenderer
{
    public function __construct(
        private readonly TextBlockRenderer $textBlockRenderer,
        private readonly ImageBlockRenderer $imageBlockRenderer,
        private readonly TableBlockRenderer $tableBlockRenderer,
        private readonly ProductsTableRenderer $productsTableRenderer,
        private readonly StructuralBlockRenderer $structuralBlockRenderer,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    public function renderZone(AbstractContainer $container, array $blocks, RenderContext $context): void
    {
        foreach ($blocks as $block) {
            $this->renderBlock($container, $block, $context);
        }
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function renderBlock(AbstractContainer $container, array $block, RenderContext $context): void
    {
        match ($block['type']) {
            'text' => $this->textBlockRenderer->render($container, $block, $context),
            'image' => $this->imageBlockRenderer->render($container, $block, $context),
            'table' => $this->tableBlockRenderer->render($container, $block, $context),
            'products_table' => $this->productsTableRenderer->render($container, $block, $context),
            'page_break' => $this->structuralBlockRenderer->pageBreak($container),
            'spacer' => $this->structuralBlockRenderer->spacer($container, $block),
            'divider' => $this->structuralBlockRenderer->divider($container, $block, $context),
        };
    }
}
