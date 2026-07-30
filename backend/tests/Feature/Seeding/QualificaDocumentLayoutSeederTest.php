<?php

use App\Models\DocumentLayout;
use App\Models\User;
use App\Services\DocumentLayouts\DocumentLayoutConfigValidator;
use Database\Seeders\DemoDocumentLayoutSeeder;
use Database\Seeders\QualificaDocumentLayoutSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

// The client's standard quote layout, transcribed from their reference
// `.docx`. What is pinned here is the contract the transcription has to
// satisfy: the config validates against the frozen allow-list, the letterhead
// is an image the layout OWNS (an image block referencing an attachment of
// another layout is a 422 on every write path), the wording stays generic
// (this is the fallback layout of every quote, not one kind of agreement), and
// a re-run converges instead of duplicating.
uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake(config('attachments.disk'));
});

/**
 * @return array<string, string> path => message, empty when the config is valid.
 */
function validateSeededLayoutConfig(DocumentLayout $layout): array
{
    return app(DocumentLayoutConfigValidator::class)->validate(
        $layout->config,
        $layout,
        $layout->module,
        User::factory()->create(),
    );
}

it('seeds the layout with its letterhead and a config the validator accepts', function (): void {
    test()->seed(QualificaDocumentLayoutSeeder::class);

    $layout = DocumentLayout::query()->where('code', QualificaDocumentLayoutSeeder::LAYOUT_CODE)->sole();
    $letterhead = $layout->images()->sole();

    expect($layout->name)->toBe('Preventivo standard')
        ->and($layout->module->value)->toBe('quotes')
        ->and($layout->is_active)->toBeTrue()
        ->and($layout->is_default)->toBeTrue()
        ->and($letterhead->original_name)->toBe(QualificaDocumentLayoutSeeder::LETTERHEAD_FILE)
        ->and(Storage::disk($letterhead->disk)->exists($letterhead->path))->toBeTrue()
        ->and(validateSeededLayoutConfig($layout))->toBe([]);
});

it('transcribes the reference document: full-bleed letterhead, rules, products table, terms', function (): void {
    test()->seed(QualificaDocumentLayoutSeeder::class);

    $layout = DocumentLayout::query()->where('code', QualificaDocumentLayoutSeeder::LAYOUT_CODE)->sole();
    $config = $layout->config;
    $bodyTypes = array_column($config['body']['blocks'], 'type');

    // The header is the A4 background of the source (595x841 points), pointing
    // at the layout's OWN attachment — the one rule the config validator
    // cannot check by shape alone.
    expect($config['header']['blocks'])->toHaveCount(1)
        ->and($config['header']['blocks'][0]['wrap'])->toBe('behind_page')
        ->and($config['header']['blocks'][0]['width'])->toBe(595)
        ->and($config['header']['blocks'][0]['height'])->toBe(841)
        ->and($config['header']['blocks'][0]['attachment_id'])->toBe($layout->images()->sole()->id)
        // The source's `sectPr`, in twips.
        ->and($config['page']['margins'])->toBe(['top' => 1985, 'right' => 1134, 'bottom' => 1560, 'left' => 1134])
        // Its four floating rules, and exactly one dynamic lines table.
        ->and(count(array_keys($bodyTypes, 'divider', true)))->toBe(4)
        ->and(count(array_keys($bodyTypes, 'products_table', true)))->toBe(1)
        ->and($config['footer']['blocks'])->toBe([]);

    $productsTable = collect($config['body']['blocks'])->firstWhere('type', 'products_table');

    // Five columns whose first cell stacks code-name over the description, and
    // the source's separate totals table folded into `totals.rows`.
    expect(array_column($productsTable['columns'], 'label'))
        ->toBe(['Descrizione', 'Quantità', 'Prezzo unitario', 'IVA', 'Totale'])
        ->and(array_sum(array_column($productsTable['columns'], 'width_pct')))->toBe(100)
        ->and($productsTable['columns'][0]['lines'])->toHaveCount(2)
        ->and(array_column($productsTable['totals']['rows'], 'variable'))
        ->toBe(['{totals.revenue_net}', '{totals.revenue_vat}']);
});

it('stays generic: no wording tied to one kind of agreement', function (): void {
    test()->seed(QualificaDocumentLayoutSeeder::class);

    $blocks = collect(DocumentLayout::query()->where('code', QualificaDocumentLayoutSeeder::LAYOUT_CODE)->sole()->config['body']['blocks']);
    $title = $blocks->firstWhere('id', 'document-title');
    $wording = $blocks->where('type', 'text')->flatMap(fn (array $block): array => array_column($block['runs'], 'text'))->implode(' ');

    // The reference `.docx` is one specific agreement; the fallback layout of
    // every quote cannot carry its title or its settlement clause.
    expect($title['runs'][0]['text'])->toBe('OFFERTA ECONOMICA')
        ->and($wording)->not->toContain('AVVALIMENTO')
        ->and($wording)->not->toContain('presente accordo');
});

it('is idempotent: a re-run refreshes the config without duplicating the row or the letterhead', function (): void {
    test()->seed(QualificaDocumentLayoutSeeder::class);

    $layout = DocumentLayout::query()->where('code', QualificaDocumentLayoutSeeder::LAYOUT_CODE)->sole();
    $letterheadId = $layout->images()->sole()->id;

    test()->seed(QualificaDocumentLayoutSeeder::class);

    $layout->refresh();

    expect(DocumentLayout::query()->where('code', QualificaDocumentLayoutSeeder::LAYOUT_CODE)->count())->toBe(1)
        ->and($layout->images()->count())->toBe(1)
        // Same attachment: a second upload would leave the first block
        // pointing at a stale id.
        ->and($layout->images()->sole()->id)->toBe($letterheadId)
        ->and($layout->config['header']['blocks'][0]['attachment_id'])->toBe($letterheadId);
});

it('claims the quotes default, demoting whatever held it', function (): void {
    // The demo fixtures already flag one of their rows as the `quotes`
    // default; this row is the module's fallback, and D-7 allows exactly one.
    test()->seed(DemoDocumentLayoutSeeder::class);
    test()->seed(QualificaDocumentLayoutSeeder::class);
    test()->seed(QualificaDocumentLayoutSeeder::class); // re-run: still exactly one default.

    expect(DocumentLayout::query()->where('module', 'quotes')->where('is_default', true)->pluck('code')->all())
        ->toBe([QualificaDocumentLayoutSeeder::LAYOUT_CODE]);
});
