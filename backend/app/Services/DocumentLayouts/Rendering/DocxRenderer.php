<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts\Rendering;

use App\Models\Quote;
use App\Models\User;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\Section as SectionStyle;

/**
 * Builds the whole PhpWord document from a validated `config` tree (spec
 * 0069 config_schema, spec 0070 rendering_contract, D-1: generated from the
 * config, never a template file): page setup, then the three zones
 * (header -> `addHeader()`, body -> the Section itself, footer ->
 * `addFooter()`), each zone's blocks dispatched by BlockRenderer.
 *
 * A zone with `blocks: []` produces NO header/footer part at all (never an
 * empty one) — `addHeader()`/`addFooter()` are only called when the zone has
 * at least one block, per spec 0070 AC-254.
 *
 * Margins are TWIPS in the config and go straight into PhpWord's own
 * Section margins (same unit, no conversion). Page size is fixed at the A4
 * portrait twips declared in RenderingUnits, swapped for landscape —
 * PhpWord's own `Style\Section::setOrientation()` performs that swap on
 * whatever pageSizeW/H are already set, so passing the (already correct,
 * order-independent) portrait values plus the requested orientation is
 * sufficient regardless of which style key PhpWord processes first.
 */
final class DocxRenderer
{
    public function __construct(private readonly BlockRenderer $blockRenderer) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public function render(array $config, Quote $quote, User $actor): PhpWord
    {
        $phpWord = new PhpWord;
        $this->applyDefaultFont($phpWord, $config['page']['default_font']);

        $margins = $config['page']['margins'];
        $usableWidthTwips = RenderingUnits::usableWidthTwips(
            RenderingUnits::A4_PORTRAIT_WIDTH_TWIPS,
            (int) $margins['left'],
            (int) $margins['right'],
        );

        $context = new RenderContext($quote, $actor, $usableWidthTwips);
        $section = $phpWord->addSection($this->pageStyle($config['page']));

        $this->renderHeader($section, $config['header']['blocks'], $context);
        $this->blockRenderer->renderZone($section, $config['body']['blocks'], $context);
        $this->renderFooter($section, $config['footer']['blocks'], $context);

        return $phpWord;
    }

    /**
     * @param  array<string, mixed>  $defaultFont
     */
    private function applyDefaultFont(PhpWord $phpWord, array $defaultFont): void
    {
        $phpWord->setDefaultFontName((string) $defaultFont['family']);
        $phpWord->setDefaultFontSize((int) $defaultFont['size']);
        $phpWord->setDefaultFontColor((string) $defaultFont['color']);
    }

    /**
     * @param  array<string, mixed>  $page
     * @return array<string, mixed>
     */
    private function pageStyle(array $page): array
    {
        $landscape = $page['orientation'] === SectionStyle::ORIENTATION_LANDSCAPE;
        $margins = $page['margins'];

        return [
            'pageSizeW' => $landscape ? RenderingUnits::A4_PORTRAIT_HEIGHT_TWIPS : RenderingUnits::A4_PORTRAIT_WIDTH_TWIPS,
            'pageSizeH' => $landscape ? RenderingUnits::A4_PORTRAIT_WIDTH_TWIPS : RenderingUnits::A4_PORTRAIT_HEIGHT_TWIPS,
            'orientation' => $page['orientation'],
            'marginTop' => $margins['top'],
            'marginRight' => $margins['right'],
            'marginBottom' => $margins['bottom'],
            'marginLeft' => $margins['left'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function renderHeader(Section $section, array $blocks, RenderContext $context): void
    {
        if ($blocks === []) {
            return;
        }

        $this->blockRenderer->renderZone($section->addHeader(), $blocks, $context);
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function renderFooter(Section $section, array $blocks, RenderContext $context): void
    {
        if ($blocks === []) {
            return;
        }

        $this->blockRenderer->renderZone($section->addFooter(), $blocks, $context);
    }
}
