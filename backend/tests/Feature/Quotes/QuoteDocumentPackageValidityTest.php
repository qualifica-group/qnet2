<?php

declare(strict_types=1);

use App\Models\DocumentLayout;
use App\Models\Quote;
use App\Models\User;
use App\Services\DocumentLayouts\Rendering\QuoteDocumentGenerator;
use Database\Factories\DocumentLayoutFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * The generated `.docx` must be a well-formed OOXML package, not merely a
 * readable zip: Word rejects the WHOLE file ("Errore durante l'apertura del
 * file") when a single part is malformed. Two real defects reached production
 * behind fixtures that only ever used plain words and round numbers —
 * PhpWord's output escaping is OFF by default (a `&` in the layout text or in
 * a client's name emitted raw into `<w:t>`), and `lineHeight` produced a float
 * in `w:line`, an attribute OOXML types as integer twips. Both are asserted
 * here against the PACKAGE, not against the text: an assertion that reads the
 * document with DOM/regex is exactly what missed them.
 */
uses(RefreshDatabase::class);

if (! function_exists('qdpvTextBlock')) {
    /**
     * @return array<string, mixed>
     */
    function qdpvTextBlock(string $text, float $lineHeight = 1.0): array
    {
        return [
            'id' => 'block-'.Str::random(6),
            'type' => 'text',
            'align' => 'left',
            'space_before' => 0,
            'space_after' => 0,
            'line_height' => $lineHeight,
            'runs' => [
                [
                    'text' => $text,
                    'field' => null,
                    'bold' => false,
                    'italic' => false,
                    'underline' => false,
                    'font' => null,
                    'size' => null,
                    'color' => null,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    function qdpvGenerate(array $config, string $quoteTitle = 'Offerta'): string
    {
        $layout = DocumentLayout::factory()->create(['module' => 'quotes', 'config' => $config]);
        $quote = Quote::factory()->create(['layout_id' => $layout->id, 'title' => $quoteTitle]);

        return app(QuoteDocumentGenerator::class)->generate($quote, $layout, User::factory()->create());
    }

    /**
     * Every XML part of the package, keyed by its name in the zip.
     *
     * @return array<string, string>
     */
    function qdpvXmlParts(string $binary): array
    {
        $path = sys_get_temp_dir().'/qdpv-'.Str::uuid()->toString().'.docx';
        file_put_contents($path, $binary);
        register_shutdown_function(static fn () => @unlink($path));

        $zip = new ZipArchive;
        expect($zip->open($path))->toBe(true);

        $parts = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);

            if (str_ends_with($name, '.xml') || str_ends_with($name, '.rels')) {
                $parts[$name] = (string) $zip->getFromName($name);
            }
        }

        $zip->close();

        return $parts;
    }
}

// ---------------------------------------------------------------------------
// XML well-formedness — the whole package, with XML metacharacters in both
// the layout's own text and the resolved data
// ---------------------------------------------------------------------------

it('produces well-formed XML in every part when text and data contain & < >', function () {
    $config = array_replace(DocumentLayoutFactory::minimalConfig(), [
        'header' => ['blocks' => [qdpvTextBlock('C.F. & P.IVA 10447341214')]],
        'body' => ['blocks' => [
            qdpvTextBlock('Spett.le {quote.title} <sede> "principale"'),
            qdpvTextBlock("Condizioni & termini dell'offerta"),
        ]],
    ]);

    $parts = qdpvXmlParts(qdpvGenerate($config, 'Rossi & Figli S.r.l.'));

    expect($parts)->toHaveKey('word/document.xml')->toHaveKey('word/header1.xml');

    foreach ($parts as $name => $xml) {
        $document = new DOMDocument;
        expect($document->loadXML($xml))->toBeTrue("Part {$name} is not well-formed XML");
    }
});

it('escapes the ampersand of resolved data instead of emitting it raw', function () {
    $config = array_replace(DocumentLayoutFactory::minimalConfig(), [
        'body' => ['blocks' => [qdpvTextBlock('{quote.title}')]],
    ]);

    $document = qdpvXmlParts(qdpvGenerate($config, 'Rossi & Figli'))['word/document.xml'];

    expect($document)->toContain('Rossi &amp; Figli')
        ->and(preg_match('/&(?!(amp|lt|gt|quot|apos|#\d+|#x[0-9a-fA-F]+);)/', $document))->toBe(0);
});

// ---------------------------------------------------------------------------
// `w:line` is an integer twips measure, for any fractional line height
// ---------------------------------------------------------------------------

it('writes a fractional line height as whole twips in w:line', function () {
    $config = array_replace(DocumentLayoutFactory::minimalConfig(), [
        'body' => ['blocks' => [qdpvTextBlock('Riga', 1.08)]],
    ]);

    $document = qdpvXmlParts(qdpvGenerate($config))['word/document.xml'];

    // 1.08 * 240 twips, the line itself included (PhpWord adds LINE_HEIGHT
    // back when the rule is `auto`), rounded: never 259.20000000000005.
    expect($document)->toContain('w:line="259"')
        ->and(preg_match('/w:[a-zA-Z]+="-?\d+\.\d+"/', $document))->toBe(0);
});
