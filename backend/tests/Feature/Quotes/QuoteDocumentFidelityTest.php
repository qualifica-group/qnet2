<?php

declare(strict_types=1);

use App\Models\DocumentLayout;
use App\Models\Quote;
use App\Models\User;
use App\Services\DocumentLayouts\Rendering\QuoteDocumentGenerator;
use Database\Seeders\QualificaDocumentLayoutSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

/**
 * spec 0070 AC-292 — end-to-end fidelity of the shipped default layout.
 *
 * Every other suite tests one side of the boundary: the seeder suite asserts
 * the transcribed `config` tree, the Rendering suite asserts that an arbitrary
 * config becomes correct OOXML. Neither proves that THE layout we ship
 * produces a document matching the client's reference file — which is the
 * actual promise of this feature. This test crosses seeder -> generator ->
 * .docx and compares against the values read out of
 * `Layout Accordo Sindacale_ Qualifica Group Training S.r.l..docx` with an XML
 * parser (they are literals here on purpose: they are the reference document,
 * not a re-derivation of our own code).
 */
uses(RefreshDatabase::class);

// Read from the reference `.docx`: `sectPr/pgSz` and `sectPr/pgMar`.
const REFERENCE_PAGE_WIDTH_TWIPS = 11906;
const REFERENCE_PAGE_HEIGHT_TWIPS = 16838;
const REFERENCE_MARGIN_TOP_TWIPS = 1985;
const REFERENCE_MARGIN_RIGHT_TWIPS = 1134;
const REFERENCE_MARGIN_BOTTOM_TWIPS = 1560;
const REFERENCE_MARGIN_LEFT_TWIPS = 1134;
// The reference products table: gridCols 4082/1770/1900/1037/1417 twips.
const REFERENCE_PRODUCT_COLUMN_RATIOS = [40, 17, 19, 10, 14];
// Header row shading of the reference products table.
const REFERENCE_TABLE_HEADER_FILL = '323E4F';

/**
 * Seed the shipped layout and render a real quote through it.
 */
function fidelityDocument(): string
{
    // The seeder writes the letterhead binary to the attachments disk.
    Storage::fake(config('attachments.disk'));

    // Through the container: the seeder injects the image service and the
    // default manager.
    app(QualificaDocumentLayoutSeeder::class)->run();

    $layout = DocumentLayout::query()
        ->where('code', QualificaDocumentLayoutSeeder::LAYOUT_CODE)
        ->sole();
    $quote = Quote::factory()->create(['layout_id' => $layout->id]);
    $actor = User::factory()->create();

    return app(QuoteDocumentGenerator::class)->generate($quote->fresh(), $layout, $actor);
}

/**
 * @return array<string, string>
 */
function fidelityPart(string $binary, string $part): string
{
    $path = tempnam(sys_get_temp_dir(), 'fidelity').'.docx';
    file_put_contents($path, $binary);

    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();
    $contents = $zip->getFromName($part);
    $zip->close();
    unlink($path);

    expect($contents)->not->toBeFalse("missing part {$part}");

    return (string) $contents;
}

it('reproduces the reference page geometry exactly (AC-292)', function (): void {
    $document = fidelityPart(fidelityDocument(), 'word/document.xml');

    expect($document)
        ->toContain('w:w="'.REFERENCE_PAGE_WIDTH_TWIPS.'"')
        ->toContain('w:h="'.REFERENCE_PAGE_HEIGHT_TWIPS.'"')
        ->toContain('w:top="'.REFERENCE_MARGIN_TOP_TWIPS.'"')
        ->toContain('w:right="'.REFERENCE_MARGIN_RIGHT_TWIPS.'"')
        ->toContain('w:bottom="'.REFERENCE_MARGIN_BOTTOM_TWIPS.'"')
        ->toContain('w:left="'.REFERENCE_MARGIN_LEFT_TWIPS.'"');
});

it('ships the full-bleed letterhead in the header, behind the text (AC-292)', function (): void {
    $binary = fidelityDocument();

    // The reference document carries its whole graphic identity as one
    // full-page image in the header — not a corner logo.
    $header = fidelityPart($binary, 'word/header1.xml');
    $rels = fidelityPart($binary, 'word/_rels/header1.xml.rels');

    expect($rels)->toContain('image');
    // PhpWord writes images as VML (`w:pict`), never DrawingML — asserted on
    // what the library really emits, not on what the spec assumed.
    expect($header)->toContain('w:pict');
    // Floating and page-anchored: a full-bleed background cannot be inline.
    expect($header)->toContain('position:absolute');
    expect($header)->toContain('mso-position-horizontal-relative:page');
    expect($header)->toContain('mso-position-vertical-relative:page');
});

it('keeps the reference products table shape: 5 columns, reference ratios, shaded header (AC-292)', function (): void {
    $document = fidelityPart(fidelityDocument(), 'word/document.xml');

    expect($document)->toContain(REFERENCE_TABLE_HEADER_FILL);

    // Usable width = page minus horizontal margins; each declared percentage
    // becomes that share of it. Compare the RATIOS, not absolute twips, so the
    // assertion survives a margin tweak.
    $usable = REFERENCE_PAGE_WIDTH_TWIPS - REFERENCE_MARGIN_LEFT_TWIPS - REFERENCE_MARGIN_RIGHT_TWIPS;

    foreach (REFERENCE_PRODUCT_COLUMN_RATIOS as $ratio) {
        $expected = (int) round($usable * $ratio / 100);
        // Allow the rounding slack of a percentage -> twips conversion.
        $found = false;
        foreach (range($expected - 2, $expected + 2) as $candidate) {
            if (str_contains($document, 'w:w="'.$candidate.'"')) {
                $found = true;
                break;
            }
        }
        expect($found)->toBeTrue("no column of ~{$expected} twips ({$ratio}%) in the products table");
    }
});

it('carries the reference static copy (AC-292)', function (): void {
    $document = fidelityPart(fidelityDocument(), 'word/document.xml');

    // Company identity block, acceptance block and payment terms, transcribed
    // from the reference document.
    foreach ([
        'Via Zoe Fontana 220',
        '10447341214',
        'Tel. 081.834.79.60',
        'OFFERTA ECONOMICA',
        'Per accettazione',
        'Qualifica Group Training srl',
        'COSTI E TERMINI DI PAGAMENTO:',
    ] as $expected) {
        expect($document)->toContain($expected);
    }
});

it('leaves no unresolved variable reference in the shipped layout (AC-233/AC-292)', function (): void {
    $document = fidelityPart(fidelityDocument(), 'word/document.xml');

    // Every `{category.key}` the layout declares must have been substituted.
    // A residual brace means the shipped layout references something the
    // resolver does not know — which would reach the client's customer.
    expect($document)->not->toMatch('/\{[a-z_]+\.[a-z_]+\}/');
});

it('has no footer, like the reference document (AC-254/AC-292)', function (): void {
    $binary = fidelityDocument();

    $path = tempnam(sys_get_temp_dir(), 'fidelity').'.docx';
    file_put_contents($path, $binary);
    $zip = new ZipArchive;
    $zip->open($path);
    $hasFooter = $zip->locateName('word/footer1.xml') !== false;
    $zip->close();
    unlink($path);

    expect($hasFooter)->toBeFalse();
});
