<?php

declare(strict_types=1);

use App\Models\DocumentLayout;
use App\Models\Quote;
use App\Models\User;
use Database\Factories\DocumentLayoutFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * `POST /api/quotes/{quote}/document` (spec 0070, MT-13): the layout
 * resolution rules (D-3), the read-only/no-persistence contract (D-2/D-4),
 * and the `generate_document` row action (AC-300/301).
 */
uses(RefreshDatabase::class);

if (! function_exists('quoteAuthUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function quoteAuthUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("quotes.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("quotes.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('qdgTextBlock')) {
    /**
     * @return array<string, mixed>
     */
    function qdgTextBlock(string $text, string $align = 'left'): array
    {
        return [
            'id' => 'block-'.Str::random(6),
            'type' => 'text',
            'align' => $align,
            'space_before' => 0,
            'space_after' => 0,
            'line_height' => 1.0,
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
     * A structurally-valid config with a non-empty header (so `header1.xml`
     * is produced, AC-230) and a body referencing `{quote.code}`/
     * `{quote.created_at}` — enough to prove variable substitution ran.
     *
     * @return array<string, mixed>
     */
    function qdgConfig(): array
    {
        return array_replace(DocumentLayoutFactory::minimalConfig(), [
            'header' => ['blocks' => [qdgTextBlock('Carta intestata')]],
            'body' => ['blocks' => [qdgTextBlock('Offerta {quote.code} del {quote.created_at}')]],
        ]);
    }

    function qdgOpenZip(string $binary): ZipArchive
    {
        $path = sys_get_temp_dir().'/qdg-test-'.Str::uuid()->toString().'.docx';
        file_put_contents($path, $binary);
        register_shutdown_function(static fn () => @unlink($path));

        $zip = new ZipArchive;
        expect($zip->open($path))->toBe(true);

        return $zip;
    }

    function qdgDocumentText(ZipArchive $zip, string $part = 'word/document.xml'): string
    {
        $document = new DOMDocument;
        $document->loadXML((string) $zip->getFromName($part));

        $text = '';

        foreach ($document->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 't') as $node) {
            $text .= $node->textContent;
        }

        return $text;
    }
}

// ---------------------------------------------------------------------------
// AC-230 — 200, PDF headers, valid docx (zip + parts) behind the conversion
// ---------------------------------------------------------------------------

it('AC-230: delivers a PDF rendered from a valid docx with the expected parts', function () {
    $actor = quoteAuthUserWith(['view']);
    $layout = DocumentLayout::factory()->create(['module' => 'quotes', 'config' => qdgConfig()]);
    $quote = Quote::factory()->create(['layout_id' => $layout->id]);
    $renderedDocx = captureDocxToPdfConversion();
    Sanctum::actingAs($actor);

    $response = $this->postJson("/api/quotes/{$quote->id}/document")->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toContain("{$quote->code}.pdf")
        ->and($response->streamedContent())->toStartWith('%PDF-');

    // The OOXML the conversion consumed: PDF is the delivered format, docx
    // stays the rendering format these assertions are about.
    $zip = qdgOpenZip($renderedDocx());

    expect($zip->locateName('word/document.xml'))->not->toBeFalse()
        ->and($zip->locateName('[Content_Types].xml'))->not->toBeFalse()
        ->and($zip->locateName('word/header1.xml'))->not->toBeFalse();

    $bodyText = qdgDocumentText($zip);
    expect($bodyText)->toContain($quote->code)
        ->and($bodyText)->not->toContain('{quote.code}');
});

// ---------------------------------------------------------------------------
// AC-260/261 — layout resolution (D-3)
// ---------------------------------------------------------------------------

it('AC-260: 422 no_layout_available when the quote has no layout and no active default exists', function () {
    $actor = quoteAuthUserWith(['view']);
    $quote = Quote::factory()->create(['layout_id' => null]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/quotes/{$quote->id}/document")
        ->assertStatus(422)
        ->assertJson(['success' => false, 'message' => __('quotes.no_layout_available')]);
});

it('AC-261: 200 using the modules current active default when the quote has no layout', function () {
    $actor = quoteAuthUserWith(['view']);
    DocumentLayout::factory()->create([
        'module' => 'quotes', 'is_default' => true, 'is_active' => true, 'config' => qdgConfig(),
    ]);
    $quote = Quote::factory()->create(['layout_id' => null]);
    captureDocxToPdfConversion();
    Sanctum::actingAs($actor);

    $this->postJson("/api/quotes/{$quote->id}/document")->assertOk();
});

it('D-3: a quotes own layout is used even after it has since been deactivated', function () {
    $actor = quoteAuthUserWith(['view']);
    $layout = DocumentLayout::factory()->create(['module' => 'quotes', 'is_active' => false, 'config' => qdgConfig()]);
    $quote = Quote::factory()->create(['layout_id' => $layout->id]);
    captureDocxToPdfConversion();
    Sanctum::actingAs($actor);

    $this->postJson("/api/quotes/{$quote->id}/document")->assertOk();
});

// ---------------------------------------------------------------------------
// AC-262 — corrupted config is 422, never 500
// ---------------------------------------------------------------------------

it('AC-262: 422 (not 500) when the resolved layouts persisted config no longer validates', function () {
    $actor = quoteAuthUserWith(['view']);
    $layout = DocumentLayout::factory()->create(['module' => 'quotes', 'config' => qdgConfig()]);
    DB::table('document_layouts')->where('id', $layout->id)->update(['config' => json_encode(['broken' => true])]);
    $quote = Quote::factory()->create(['layout_id' => $layout->id]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/quotes/{$quote->id}/document")
        ->assertStatus(422)
        ->assertJson(['success' => false]);
});

// ---------------------------------------------------------------------------
// AC-263 — 403/404
// ---------------------------------------------------------------------------

it('AC-263: 403 without quotes.view', function () {
    $actor = quoteAuthUserWith([]);
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/quotes/{$quote->id}/document")->assertForbidden();
});

it('AC-263: 404 on a nonexistent quote', function () {
    $actor = quoteAuthUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/quotes/999999/document')->assertNotFound();
});

// ---------------------------------------------------------------------------
// AC-264/265 — read-only, no persistence
// ---------------------------------------------------------------------------

it('AC-264: generation writes no persistent file to the local disk', function () {
    Storage::fake('local');
    $actor = quoteAuthUserWith(['view']);
    $layout = DocumentLayout::factory()->create(['module' => 'quotes', 'config' => qdgConfig()]);
    $quote = Quote::factory()->create(['layout_id' => $layout->id]);
    captureDocxToPdfConversion();
    Sanctum::actingAs($actor);

    $this->postJson("/api/quotes/{$quote->id}/document")->assertOk();

    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

it('AC-265: generation is a pure read: updated_at, aggregates and activity log are unchanged', function () {
    $actor = quoteAuthUserWith(['view']);
    $layout = DocumentLayout::factory()->create(['module' => 'quotes', 'config' => qdgConfig()]);
    $quote = Quote::factory()->create([
        'layout_id' => $layout->id,
        'revenue_net' => 1000.00,
        'revenue_vat' => 220.00,
    ]);
    $updatedAtBefore = $quote->updated_at;
    $activityCountBefore = $quote->activities()->count();
    captureDocxToPdfConversion();
    Sanctum::actingAs($actor);

    $this->postJson("/api/quotes/{$quote->id}/document")->assertOk();

    $quote->refresh();

    expect($quote->updated_at->equalTo($updatedAtBefore))->toBeTrue()
        ->and((float) $quote->revenue_net)->toBe(1000.00)
        ->and((float) $quote->revenue_vat)->toBe(220.00)
        ->and($quote->activities()->count())->toBe($activityCountBefore);
});

// ---------------------------------------------------------------------------
// AC-300/301 — the generate_document row action
// ---------------------------------------------------------------------------

it('AC-300: GET /api/tables/quotes/columns declares generate_document, gated by quotes.view', function () {
    // resolveActions() strips the actions the actor's permission does not
    // cover (and the `permission` key itself, spec 0004): with `quotes.view`
    // the action is present; without it, it is omitted entirely.
    $actorWithView = quoteAuthUserWith(['viewAny', 'view']);
    Sanctum::actingAs($actorWithView);

    $columns = $this->getJson('/api/tables/quotes/columns')->assertOk()->json('data');
    $action = collect($columns['actions'])->firstWhere('key', 'generate_document');

    expect($action)->not->toBeNull()
        ->and($action['label'])->toBe('actions.generatePdf');

    $actorWithoutView = quoteAuthUserWith(['viewAny']);
    Sanctum::actingAs($actorWithoutView);

    $columns = $this->getJson('/api/tables/quotes/columns')->assertOk()->json('data');
    expect(collect($columns['actions'])->firstWhere('key', 'generate_document'))->toBeNull();
});

it('AC-301: the pre-existing row actions are unchanged and generate_document is gated like view', function () {
    $fullActor = quoteAuthUserWith(['viewAny', 'view', 'update', 'delete', 'viewActivity']);
    $quote = Quote::factory()->create();
    Sanctum::actingAs($fullActor);

    $response = $this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $quote->id);

    expect($row['actions'])->toEqual(['view', 'delete', 'activity', 'generate_document']);

    $noViewActor = quoteAuthUserWith(['viewAny']);
    Sanctum::actingAs($noViewActor);

    $response = $this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $quote->id);

    expect($row['actions'])->not->toContain('generate_document');
});
