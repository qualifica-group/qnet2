<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\DocumentLayouts\Rendering\QuoteDocumentGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

require_once __DIR__.'/RenderingTestSupport.php';

// spec 0070 — image (behind_page/inline), divider, page fields, page_break/
// spacer/static table (AC-250..253/255).

// ---------------------------------------------------------------------------
// AC-250 — behind_page image: media present, header rels declared, floating
// ---------------------------------------------------------------------------

it('a behind_page header image is floating (behind, page-anchored) with its media and header rels declared (AC-250)', function () {
    Storage::fake('local');

    $layout = dlrLayoutWithImage(fn (int $attachmentId): array => dlrDocConfig([
        'header' => ['blocks' => [dlrImageBlockConfig($attachmentId, ['wrap' => 'behind_page', 'width' => 595, 'height' => 842])]],
    ]));

    $binary = app(QuoteDocumentGenerator::class)->generate(dlrFullQuote(), $layout, User::factory()->create());
    $zip = dlrOpenZip($binary);

    $mediaFound = false;

    for ($i = 0; $i < $zip->numFiles; $i++) {
        if (str_starts_with($zip->getNameIndex($i), 'word/media/')) {
            $mediaFound = true;
        }
    }

    expect($mediaFound)->toBeTrue()
        ->and($zip->locateName('word/_rels/header1.xml.rels'))->not->toBe(false);

    // "Behind text" for this library's VML writer is expressed as a negative
    // z-index (there is no `w10:wrap` element for wrap=behind/infront — see
    // Frame's Word2007 style writer: it computes z-index instead of writing
    // the wrap element for those two types), anchored to the page via
    // mso-position-*-relative:page and positioned absolutely at (0,0).
    $headerXml = (string) $zip->getFromName('word/header1.xml');
    expect($headerXml)->toContain('z-index:-2147483647')
        ->and($headerXml)->toContain('mso-position-horizontal-relative:page')
        ->and($headerXml)->toContain('mso-position-vertical-relative:page')
        ->and($headerXml)->toContain('position:absolute')
        ->and($headerXml)->not->toContain('w10:wrap');
});

// ---------------------------------------------------------------------------
// AC-251 — inline image: rendered inline, declared dimensions in points
// ---------------------------------------------------------------------------

it('an inline image block is in the text flow with its declared width/height (points) in the style string (AC-251)', function () {
    Storage::fake('local');

    $layout = dlrLayoutWithImage(fn (int $attachmentId): array => dlrDocConfig([
        'body' => ['blocks' => [dlrImageBlockConfig($attachmentId, ['wrap' => 'inline', 'width' => 120, 'height' => 60])]],
    ]));

    $binary = app(QuoteDocumentGenerator::class)->generate(dlrFullQuote(), $layout, User::factory()->create());
    $zip = dlrOpenZip($binary);
    $documentXml = (string) $zip->getFromName('word/document.xml');

    // CAVEAT (documented on ImageBlockRenderer): phpoffice/phpword 1.4 writes
    // every image via legacy VML (w:pict/v:shape), not DrawingML/EMU — width
    // and height are emitted in POINTS inside the v:shape `style` attribute,
    // not as a `wp:extent` EMU pair. This asserts the real, verified output.
    expect($documentXml)->toContain('width:120pt')
        ->and($documentXml)->toContain('height:60pt')
        ->and($documentXml)->not->toContain('wrap type="behind"');
});

// ---------------------------------------------------------------------------
// AC-252 — divider: bottom border, no text
// ---------------------------------------------------------------------------

it('a divider block renders a paragraph with a bottom border and no text (AC-252)', function () {
    $config = dlrDocConfig(['body' => ['blocks' => [dlrDividerBlock(widthPct: 100, thickness: 6, color: 'FF0000')]]]);

    $zip = dlrOpenZip(dlrRender($config, dlrFullQuote()));
    $documentXml = (string) $zip->getFromName('word/document.xml');

    expect($documentXml)->toContain('<w:bottom')
        ->and($documentXml)->toContain('w:sz="6"')
        ->and($documentXml)->toContain('w:color="FF0000"');

    expect(dlrDocumentText($zip))->toBe('');
});

// ---------------------------------------------------------------------------
// AC-253 — PAGE/NUMPAGES render as fields (w:instrText), not literal text
// ---------------------------------------------------------------------------

it('renders {field: page} and {field: total_pages} as PAGE/NUMPAGES instrText fields, not literal text (AC-253)', function () {
    $config = dlrDocConfig(['footer' => ['blocks' => [
        dlrTextBlock([
            dlrRun(['text' => 'ignored-page', 'field' => 'page']),
            dlrRun(['text' => ' / ']),
            dlrRun(['text' => 'ignored-total', 'field' => 'total_pages']),
        ]),
    ]]]);

    $zip = dlrOpenZip(dlrRender($config, dlrFullQuote()));
    $footerXml = (string) $zip->getFromName('word/footer1.xml');

    expect($footerXml)->toContain('<w:instrText')
        ->and($footerXml)->toContain('PAGE')
        ->and($footerXml)->toContain('NUMPAGES')
        ->and($footerXml)->not->toContain('ignored-page')
        ->and($footerXml)->not->toContain('ignored-total');
});

// ---------------------------------------------------------------------------
// AC-255 — page_break, spacer, static table
// ---------------------------------------------------------------------------

it('page_break renders a page break, spacer renders its converted twips spacing (AC-255)', function () {
    $config = dlrDocConfig(['body' => ['blocks' => [
        dlrTextBlock([dlrRun(['text' => 'Before'])]),
        ['id' => 'break-1', 'type' => 'page_break'],
        ['id' => 'spacer-1', 'type' => 'spacer', 'height' => 10],
        dlrTextBlock([dlrRun(['text' => 'After'])]),
    ]]]);

    $zip = dlrOpenZip(dlrRender($config, dlrFullQuote()));
    $documentXml = (string) $zip->getFromName('word/document.xml');

    expect($documentXml)->toContain('<w:br w:type="page"/>')
        ->and($documentXml)->toContain('w:after="200"'); // 10pt * 20 twips/pt
});

it('a static table block renders rows/cells with gridSpan, cell background and borders (AC-255)', function () {
    $tableBlock = [
        'id' => 'table-1',
        'type' => 'table',
        'width_pct' => 100,
        'borders' => ['size' => 4, 'color' => '000000'],
        'columns' => [['width_pct' => 50], ['width_pct' => 50]],
        'rows' => [
            [
                'is_header' => false,
                'cells' => [
                    ['col_span' => 2, 'background' => 'DEEAF6', 'vertical_align' => 'center', 'blocks' => [dlrTextBlock([dlrRun(['text' => 'Spanning cell'])])]],
                ],
            ],
        ],
    ];

    $config = dlrDocConfig(['body' => ['blocks' => [$tableBlock]]]);
    $zip = dlrOpenZip(dlrRender($config, dlrFullQuote()));

    $rows = dlrExtractTable($zip);
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['cells'][0]['grid_span'])->toBe(2)
        ->and(dlrRowText($rows[0]))->toBe(['Spanning cell']);

    $documentXml = (string) $zip->getFromName('word/document.xml');
    expect($documentXml)->toContain('w:fill="DEEAF6"')
        ->and($documentXml)->toContain('w:sz="4"');
});
