<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts\Rendering;

/**
 * The unit constants frozen by spec 0070's rendering_contract: single source
 * of truth so no magic number is repeated across DocxRenderer and the block
 * renderers (engineering.md §6/§8).
 *
 * Margins/spacing in the `config` column are already TWIPS (PhpWord's own
 * unit for Section/Paragraph spacing) — they are passed straight through,
 * never converted. Font sizes/image dimensions in `config` are POINTS, which
 * is also PhpWord's `Style\Image`/`Style\Font` default unit — again passed
 * straight through. The one REAL conversion this module performs is
 * percentage -> twips for column/table/divider widths, done once against the
 * page's usable width (spec: "calcolata una volta e riusata").
 */
final class RenderingUnits
{
    /** A4 portrait width in twips (1/1440 inch), per spec 0070's rendering_contract. */
    public const int A4_PORTRAIT_WIDTH_TWIPS = 11906;

    /** A4 portrait height in twips, per spec 0070's rendering_contract. */
    public const int A4_PORTRAIT_HEIGHT_TWIPS = 16838;

    /** 1 point = 20 twips (1440 twips/inch over 72 points/inch). */
    public const int POINTS_TO_TWIPS = 20;

    /**
     * 1 point = 12700 EMU (914400 EMU/inch over 72 points/inch) — declared
     * for documentation/tests even though PhpWord's own Style\Image accepts
     * plain points and performs this conversion internally (see
     * ImageBlockRenderer's docblock for the caveat: this library version
     * actually emits legacy VML, not DrawingML/EMU, for every image).
     */
    public const int POINTS_TO_EMU = 12700;

    public const string DATE_FORMAT = 'd/m/Y';

    public const string DATE_TIME_FORMAT = 'd/m/Y H:i';

    private function __construct() {}

    /**
     * The page's usable width in twips (page width minus left/right margins)
     * — computed ONCE by DocxRenderer and reused by every block that converts
     * a `width_pct`/`thickness` percentage into an absolute twips value
     * (table/column/divider widths).
     */
    public static function usableWidthTwips(int $pageWidthTwips, int $marginLeftTwips, int $marginRightTwips): int
    {
        return $pageWidthTwips - $marginLeftTwips - $marginRightTwips;
    }

    /**
     * Convert an integer percentage (1..100) of $usableWidthTwips into an
     * absolute twips value — the single conversion point spec 0070 requires
     * ("è l'unico posto dove la percentuale diventa assoluta").
     */
    public static function percentToTwips(int $percent, int $usableWidthTwips): int
    {
        return (int) round($usableWidthTwips * $percent / 100);
    }
}
