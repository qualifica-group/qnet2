<?php

declare(strict_types=1);

namespace Database\Seeders\QualificaCatalog;

/**
 * The client's STANDARD quote layout: the page furniture (letterhead, company
 * address, rules, lines table, totals, signatures, payment terms) transcribed
 * block by block from their reference `.docx` ("Layout Accordo Sindacale_
 * Qualifica Group Training S.r.l."), with everything specific to that one
 * agreement generalised — the title is "OFFERTA ECONOMICA", not "... E
 * CONTRATTO DI AVVALIMENTO", and the terms settle "la presente offerta", not
 * "il presente accordo". It is the layout EVERY quote falls back to (the
 * module default, see QualificaDocumentLayoutSeeder), so nothing in it may
 * assume a particular kind of deal; a document type that needs its own
 * wording is a second layout, cloned from this one in the configurator.
 *
 * Pure data: QualificaDocumentLayoutSeeder holds the logic (the row, the
 * uploaded letterhead), this file holds the `config` tree.
 *
 * The measurements come from the OOXML of that file, read — not remembered:
 * page size/margins from its `sectPr`, colors and font sizes from the runs
 * (Word stores sizes in HALF-points, halved here), the letterhead extent from
 * the header's anchored drawing (7551317 x 10677155 EMU / 12700 = 595 x 841
 * points, a full-bleed A4 background, hence `wrap: behind_page`), the four
 * horizontal rules from the floating `v:line` shapes (strokeweight 1pt =
 * RULE_THICKNESS eighths, color A5A5A5).
 *
 * Two deliberate departures from a literal transcription, both because the
 * frozen config contract (spec 0069) has no equivalent primitive:
 *   - the source's `${...}` placeholders are the LEGACY engine's, not ours:
 *     they are mapped onto this module's variable catalogue
 *     (`{quote.code}`, `{client.name}`, `{totals.revenue_gross}`, ...);
 *   - the signature area, aligned with tab stops in Word, is a borderless
 *     two-column `table` block: a `run` has no tab and padding it with spaces
 *     would not survive a font change.
 *
 * UNITS (spec 0069): page margins and paragraph spacing in TWIPS; font sizes,
 * image sides and spacer heights in POINTS; widths in PERCENT; colors RRGGBB
 * without `#`; `divider.thickness` in EIGHTHS of a point.
 */
final class StandardQuoteLayout
{
    private const float LINE_HEIGHT = 1.08;

    /** Word's own default (`docDefaults`: `w:after="160"`), inherited by nearly every paragraph of the source. */
    private const int PARAGRAPH_SPACE_AFTER = 160;

    private const string FONT_FAMILY = 'Calibri';

    /** The font of the source's two accented lines ("Offerta n°", "Per accettazione"). */
    private const string ACCENT_FONT = 'Trebuchet MS';

    private const string ACCENT_COLOR = '2B3B4C';

    private const string TITLE_COLOR = '3B3838';

    private const string TEXT_COLOR = '262626';

    private const string TABLE_HEADER_BACKGROUND = '323E4F';

    private const string RULE_COLOR = 'A5A5A5';

    /** Eighths of a point: the source's rules are 1pt strokes. */
    private const int RULE_THICKNESS = 8;

    /**
     * The rules float on their own anchor in the source, so they carry no
     * paragraph spacing to copy: one uniform gap keeps them from touching the
     * text they separate.
     */
    private const int RULE_SPACE_AFTER = 120;

    private const int SMALL_SIZE = 8;

    private const int BODY_SIZE = 9;

    private const int TITLE_SIZE = 14;

    private const int TABLE_HEADER_SIZE = 10;

    /** The terms block at the foot of the page is set in 6pt in the source. */
    private const int TERMS_SIZE = 6;

    private const int LETTERHEAD_WIDTH_POINTS = 595;

    private const int LETTERHEAD_HEIGHT_POINTS = 841;

    /** Stands in for the four empty paragraphs the source leaves above the products table. */
    private const int TITLE_GAP_POINTS = 60;

    /**
     * @return array<string, mixed>
     */
    public static function config(int $letterheadAttachmentId): array
    {
        return [
            'version' => 1,
            'page' => self::page(),
            'header' => ['blocks' => [self::letterhead($letterheadAttachmentId)]],
            'body' => ['blocks' => self::bodyBlocks()],
            // The source declares no footer: its page furniture is entirely in
            // the full-bleed letterhead.
            'footer' => ['blocks' => []],
        ];
    }

    /**
     * The scaffold persisted while the row is being created: a layout's images
     * can only be attached once the layout exists, so the letterhead's
     * `attachment_id` is not knowable yet (see QualificaDocumentLayoutSeeder).
     *
     * @return array<string, mixed>
     */
    public static function emptyConfig(): array
    {
        return [
            'version' => 1,
            'page' => self::page(),
            'header' => ['blocks' => []],
            'body' => ['blocks' => []],
            'footer' => ['blocks' => []],
        ];
    }

    /**
     * From the source's `sectPr`: A4 portrait, margins top 1985 / right 1134 /
     * bottom 1560 / left 1134 twips, body text at 11pt (`w:sz="22"`).
     *
     * @return array<string, mixed>
     */
    private static function page(): array
    {
        return [
            'format' => 'A4',
            'orientation' => 'portrait',
            'margins' => ['top' => 1985, 'right' => 1134, 'bottom' => 1560, 'left' => 1134],
            'default_font' => ['family' => self::FONT_FAMILY, 'size' => 11, 'color' => '000000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function letterhead(int $attachmentId): array
    {
        return [
            'id' => 'letterhead',
            'type' => 'image',
            'attachment_id' => $attachmentId,
            'width' => self::LETTERHEAD_WIDTH_POINTS,
            'height' => self::LETTERHEAD_HEIGHT_POINTS,
            'align' => 'center',
            'wrap' => 'behind_page',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function bodyBlocks(): array
    {
        return [
            self::divider('rule-above-company'),
            ...self::companyAddress(),
            self::divider('rule-below-company'),
            ...self::quoteHeading(),
            self::documentTitle(),
            ['id' => 'title-gap', 'type' => 'spacer', 'height' => self::TITLE_GAP_POINTS],
            self::productsTable(),
            self::grandTotal(),
            self::divider('rule-above-signatures'),
            self::signatures(),
            self::divider('rule-above-terms'),
            ...self::paymentTerms(),
        ];
    }

    /**
     * The right-aligned company address: ONE paragraph with two line breaks in
     * the source, three paragraphs here (a `run` carries no break), so the
     * first two close with no trailing space.
     *
     * @return list<array<string, mixed>>
     */
    private static function companyAddress(): array
    {
        $lines = [
            'Via Zoe Fontana 220 - 00131 Roma (RM) - C.F. & P.IVA 10447341214',
            'Tel. 081.834.79.60 - Fax 081.010.41.08',
            'www.qualificagroup.it - offerte@qualificagroup.it',
        ];

        return array_map(
            static fn (string $line, int $index): array => self::text(
                'company-address-'.($index + 1),
                'right',
                [self::run($line, self::SMALL_SIZE, null, self::FONT_FAMILY)],
                $index === array_key_last($lines) ? self::PARAGRAPH_SPACE_AFTER : 0,
            ),
            $lines,
            array_keys($lines),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function quoteHeading(): array
    {
        return [
            self::text('quote-reference', 'left', [
                self::run('Offerta n°', self::BODY_SIZE, self::ACCENT_COLOR, self::ACCENT_FONT),
                self::run(' {quote.code} del ', null, null, self::FONT_FAMILY),
                self::run('{quote.created_at}'),
            ]),
            self::text('client-salutation', 'right', [self::run('Spettabile:', self::BODY_SIZE, null, self::FONT_FAMILY)]),
            self::text('client-name', 'right', [self::run('{client.name}', self::BODY_SIZE, null, self::FONT_FAMILY)], 0),
            self::text('client-address', 'right', [self::run('{client.address}', self::BODY_SIZE, null, self::FONT_FAMILY)]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function documentTitle(): array
    {
        return self::text('document-title', 'center', [
            self::run('OFFERTA ECONOMICA', self::TITLE_SIZE, self::TITLE_COLOR, null, true),
        ]);
    }

    /**
     * The source's five-column line table, plus its separate two-row totals
     * table folded into `totals.rows` (the block renders both). Column widths
     * are its `tblGrid` (4082/1770/1900/1037/1417 twips) normalised to percent.
     *
     * @return array<string, mixed>
     */
    private static function productsTable(): array
    {
        return [
            'id' => 'quote-lines',
            'type' => 'products_table',
            'source' => 'offer_lines',
            'width_pct' => 100,
            'borders' => ['size' => 4, 'color' => '000000'],
            'show_header' => true,
            'header_background' => self::TABLE_HEADER_BACKGROUND,
            'columns' => [
                self::productColumn('Descrizione', 40, 'left', [
                    self::productLine(['code', 'name'], '-'),
                    self::productLine(['description'], ''),
                ]),
                self::productColumn('Quantità', 17, 'left', [self::productLine(['quantity'], '')]),
                self::productColumn('Prezzo unitario', 19, 'left', [self::productLine(['unit_price'], '')]),
                self::productColumn('IVA', 10, 'left', [self::productLine(['vat_rate'], '')]),
                self::productColumn('Totale', 14, 'right', [self::productLine(['total_amount'], '')]),
            ],
            'totals' => [
                'show' => true,
                'rows' => [
                    ['label' => 'Totale imponibile', 'variable' => '{totals.revenue_net}', 'bold' => false],
                    ['label' => 'IVA', 'variable' => '{totals.revenue_vat}', 'bold' => false],
                ],
            ],
            'empty_text' => 'Nessuna riga presente in offerta.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function grandTotal(): array
    {
        return self::text('grand-total', 'right', [
            self::run('Totale offerta :  {totals.revenue_gross}', null, null, self::FONT_FAMILY, true),
        ]);
    }

    /**
     * The two signature rules and their captions, tab-aligned in the source
     * (see the class docblock on why they are a borderless table here).
     *
     * @return array<string, mixed>
     */
    private static function signatures(): array
    {
        return [
            'id' => 'signatures',
            'type' => 'table',
            'width_pct' => 100,
            'borders' => null,
            'columns' => [['width_pct' => 55], ['width_pct' => 45]],
            'rows' => [
                self::signatureRow(
                    self::text('signature-rule-client', 'left', [self::run('_ _ _ _ _ _ _ _ _ _ _ _ _ _ _', self::SMALL_SIZE, '000000', null, true)], 0),
                    self::text('signature-rule-supplier', 'left', [self::run('_ _ _ _ _ _ _ _ _ _', self::SMALL_SIZE, '000000', null, true)], 0),
                ),
                self::signatureRow(
                    self::text('signature-caption-client', 'left', [self::run('Per accettazione', self::SMALL_SIZE, self::ACCENT_COLOR, self::ACCENT_FONT)], 0),
                    self::text('signature-caption-supplier', 'left', [self::run('Qualifica Group Training srl', self::SMALL_SIZE, self::ACCENT_COLOR, self::ACCENT_FONT)], 0),
                ),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     * @return array<string, mixed>
     */
    private static function signatureRow(array $left, array $right): array
    {
        return [
            'is_header' => false,
            'cells' => array_map(
                static fn (array $block): array => [
                    'col_span' => 1,
                    'background' => null,
                    'vertical_align' => 'top',
                    'blocks' => [$block],
                ],
                [$left, $right],
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function paymentTerms(): array
    {
        $paragraphs = [
            ['id' => 'terms-title', 'text' => 'COSTI E TERMINI DI PAGAMENTO:', 'bold' => true],
            ['id' => 'terms-acceptance', 'text' => 'In caso di accettazione: a) Si prega di rinviare la presente offerta per accettazione all’indirizzo offerte@qualificagroup.it', 'bold' => false],
            ['id' => 'terms-method-title', 'text' => 'Modalità di pagamento:', 'bold' => true],
            ['id' => 'terms-method', 'text' => 'Il compenso verrà versato dal Committente a Qualifica Group Training S.r.l. con le seguenti modalità:', 'bold' => false],
            ['id' => 'terms-settlement', 'text' => 'saldo alla sottoscrizione della presente offerta', 'bold' => false],
        ];

        return array_map(
            static fn (array $paragraph): array => self::text(
                $paragraph['id'],
                'left',
                [self::run($paragraph['text'], self::TERMS_SIZE, self::TEXT_COLOR, self::FONT_FAMILY, $paragraph['bold'])],
                0,
            ),
            $paragraphs,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    private static function productColumn(string $label, int $widthPct, string $align, array $lines): array
    {
        return ['lines' => $lines, 'label' => $label, 'width_pct' => $widthPct, 'align' => $align];
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private static function productLine(array $keys, string $separator): array
    {
        return ['keys' => $keys, 'separator' => $separator, 'bold' => false, 'italic' => false, 'size' => self::BODY_SIZE];
    }

    /**
     * @param  list<array<string, mixed>>  $runs
     * @return array<string, mixed>
     */
    private static function text(string $id, string $align, array $runs, int $spaceAfter = self::PARAGRAPH_SPACE_AFTER): array
    {
        return [
            'id' => $id,
            'type' => 'text',
            'align' => $align,
            'space_before' => 0,
            'space_after' => $spaceAfter,
            'line_height' => self::LINE_HEIGHT,
            'runs' => $runs,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function run(string $text, ?int $size = null, ?string $color = null, ?string $font = null, bool $bold = false): array
    {
        return [
            'text' => $text,
            'field' => null,
            'bold' => $bold,
            'italic' => false,
            'underline' => false,
            'font' => $font,
            'size' => $size,
            'color' => $color,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function divider(string $id): array
    {
        return [
            'id' => $id,
            'type' => 'divider',
            'width_pct' => 100,
            'thickness' => self::RULE_THICKNESS,
            'color' => self::RULE_COLOR,
            'space_before' => 0,
            'space_after' => self::RULE_SPACE_AFTER,
        ];
    }
}
