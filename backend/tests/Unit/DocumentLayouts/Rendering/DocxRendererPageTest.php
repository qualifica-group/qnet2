<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

require_once __DIR__.'/RenderingTestSupport.php';

// spec 0070 — DocxRenderer: archive validity, page/pgMar, empty-zone parts.

// ---------------------------------------------------------------------------
// AC-230/AC-231 — a valid, well-formed OOXML archive
// ---------------------------------------------------------------------------

it('produces a ZIP archive with document.xml and [Content_Types].xml, every declared part present, every XML well-formed (AC-230/AC-231)', function () {
    $quote = dlrFullQuote();
    $binary = dlrRender(dlrDocConfig(), $quote);

    $zip = dlrOpenZip($binary);

    expect($zip->locateName('word/document.xml'))->not->toBe(false)
        ->and($zip->locateName('[Content_Types].xml'))->not->toBe(false);

    $declaredParts = dlrDeclaredContentTypeParts($zip);
    expect($declaredParts)->not->toBeEmpty();

    foreach ($declaredParts as $part) {
        expect($zip->locateName($part))->not->toBe(false, "declared part {$part} is missing from the archive");
    }

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);

        if (! str_ends_with($name, '.xml') && ! str_ends_with($name, '.rels')) {
            continue;
        }

        dlrAssertWellFormedXml((string) $zip->getFromName($name));
    }
});

// ---------------------------------------------------------------------------
// AC-232 — page size, margins, orientation swap
// ---------------------------------------------------------------------------

it('renders pgSz 11906x16838 and the exact configured pgMar in portrait (AC-232)', function () {
    $config = dlrDocConfig([
        'page' => [
            'format' => 'A4',
            'orientation' => 'portrait',
            'margins' => ['top' => 1985, 'right' => 1134, 'bottom' => 1560, 'left' => 1134],
            'default_font' => ['family' => 'Calibri', 'size' => 10, 'color' => '000000'],
        ],
    ]);

    $zip = dlrOpenZip(dlrRender($config, dlrFullQuote()));
    $documentXml = new DOMDocument;
    $documentXml->loadXML((string) $zip->getFromName('word/document.xml'));

    $pgSz = $documentXml->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'pgSz')->item(0);
    expect($pgSz->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w'))->toBe('11906')
        ->and($pgSz->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'h'))->toBe('16838')
        ->and($pgSz->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'orient'))->toBe('portrait');

    $pgMar = $documentXml->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'pgMar')->item(0);
    $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    expect($pgMar->getAttributeNS($ns, 'top'))->toBe('1985')
        ->and($pgMar->getAttributeNS($ns, 'right'))->toBe('1134')
        ->and($pgMar->getAttributeNS($ns, 'bottom'))->toBe('1560')
        ->and($pgMar->getAttributeNS($ns, 'left'))->toBe('1134');
});

it('inverts pgSz width/height for landscape orientation (AC-232)', function () {
    $config = dlrDocConfig([
        'page' => [
            'format' => 'A4',
            'orientation' => 'landscape',
            'margins' => ['top' => 1134, 'right' => 1134, 'bottom' => 1134, 'left' => 1134],
            'default_font' => ['family' => 'Calibri', 'size' => 10, 'color' => '000000'],
        ],
    ]);

    $zip = dlrOpenZip(dlrRender($config, dlrFullQuote()));
    $documentXml = new DOMDocument;
    $documentXml->loadXML((string) $zip->getFromName('word/document.xml'));
    $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    $pgSz = $documentXml->getElementsByTagNameNS($ns, 'pgSz')->item(0);
    expect($pgSz->getAttributeNS($ns, 'w'))->toBe('16838')
        ->and($pgSz->getAttributeNS($ns, 'h'))->toBe('11906')
        ->and($pgSz->getAttributeNS($ns, 'orient'))->toBe('landscape');
});

// ---------------------------------------------------------------------------
// AC-254 — an empty zone produces no header/footer part
// ---------------------------------------------------------------------------

it('produces no header/footer part when a zone has blocks: [] (AC-254)', function () {
    $config = dlrDocConfig(['header' => ['blocks' => []], 'footer' => ['blocks' => []]]);

    $zip = dlrOpenZip(dlrRender($config, dlrFullQuote()));

    expect($zip->locateName('word/header1.xml'))->toBe(false)
        ->and($zip->locateName('word/footer1.xml'))->toBe(false);

    $documentXml = (string) $zip->getFromName('word/document.xml');
    expect($documentXml)->not->toContain('headerReference')
        ->and($documentXml)->not->toContain('footerReference');
});

it('produces a header part when the header zone has at least one block', function () {
    $config = dlrDocConfig(['header' => ['blocks' => [dlrTextBlock([dlrRun(['text' => 'Header text'])])]]]);

    $zip = dlrOpenZip(dlrRender($config, dlrFullQuote()));

    expect($zip->locateName('word/header1.xml'))->not->toBe(false);
});
