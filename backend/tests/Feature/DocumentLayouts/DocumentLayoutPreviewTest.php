<?php

declare(strict_types=1);

use App\Models\DocumentLayout;
use App\Models\Quote;
use App\Models\User;
use Database\Factories\DocumentLayoutFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * `POST /api/document-layouts/{documentLayout}/preview` (spec 0070, MT-13):
 * quote resolution (explicit `quote_id` / most recent visible / deterministic
 * sample), authorization on both the layout and the resolved quote, and the
 * `{layout.code}-preview.pdf` filename contract.
 */
uses(RefreshDatabase::class);

if (! function_exists('documentLayoutUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function documentLayoutUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("document-layouts.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("document-layouts.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('grantQuotesView')) {
    function grantQuotesView(User $user): void
    {
        Permission::findOrCreate('quotes.view');
        $user->givePermissionTo('quotes.view');
    }
}

if (! function_exists('dlpConfig')) {
    /**
     * @return array<string, mixed>
     */
    function dlpConfig(): array
    {
        return array_replace(DocumentLayoutFactory::minimalConfig(), [
            'body' => ['blocks' => [[
                'id' => 'block-'.Str::random(6),
                'type' => 'text',
                'align' => 'left',
                'space_before' => 0,
                'space_after' => 0,
                'line_height' => 1.0,
                'runs' => [[
                    'text' => 'Offerta {quote.code}',
                    'field' => null,
                    'bold' => false,
                    'italic' => false,
                    'underline' => false,
                    'font' => null,
                    'size' => null,
                    'color' => null,
                ]],
            ]]],
        ]);
    }

    function dlpOpenZip(string $binary): ZipArchive
    {
        $path = sys_get_temp_dir().'/dlp-test-'.Str::uuid()->toString().'.docx';
        file_put_contents($path, $binary);
        register_shutdown_function(static fn () => @unlink($path));

        $zip = new ZipArchive;
        expect($zip->open($path))->toBe(true);

        return $zip;
    }

    function dlpDocumentText(ZipArchive $zip): string
    {
        $document = new DOMDocument;
        $document->loadXML((string) $zip->getFromName('word/document.xml'));

        $text = '';

        foreach ($document->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 't') as $node) {
            $text .= $node->textContent;
        }

        return $text;
    }
}

// ---------------------------------------------------------------------------
// AC-270 — sample data on an empty database
// ---------------------------------------------------------------------------

it('AC-270: 200 with deterministic sample data when quote_id is omitted and no quote exists', function () {
    $actor = documentLayoutUserWith(['view']);
    $layout = DocumentLayout::factory()->create(['module' => 'quotes', 'config' => dlpConfig()]);
    $renderedDocx = captureDocxToPdfConversion();
    Sanctum::actingAs($actor);

    expect(Quote::count())->toBe(0);

    $response = $this->postJson("/api/document-layouts/{$layout->id}/preview", [])->assertOk();

    expect($response->streamedContent())->toStartWith('%PDF-');

    $zip = dlpOpenZip($renderedDocx());
    expect($zip->locateName('word/document.xml'))->not->toBeFalse();
});

// ---------------------------------------------------------------------------
// AC-271 — explicit quote_id
// ---------------------------------------------------------------------------

it('AC-271: 200 rendering the requested quotes own data via quote_id', function () {
    $actor = documentLayoutUserWith(['view']);
    grantQuotesView($actor);
    $layout = DocumentLayout::factory()->create(['module' => 'quotes', 'config' => dlpConfig()]);
    $quote = Quote::factory()->create();
    $renderedDocx = captureDocxToPdfConversion();
    Sanctum::actingAs($actor);

    $this->postJson("/api/document-layouts/{$layout->id}/preview", ['quote_id' => $quote->id])->assertOk();

    $text = dlpDocumentText(dlpOpenZip($renderedDocx()));
    expect($text)->toContain($quote->code);
});

it('AC-271: 404 with a nonexistent quote_id', function () {
    $actor = documentLayoutUserWith(['view']);
    grantQuotesView($actor);
    $layout = DocumentLayout::factory()->create(['module' => 'quotes', 'config' => dlpConfig()]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/document-layouts/{$layout->id}/preview", ['quote_id' => 999999])
        ->assertNotFound();
});

it('AC-271: 403 when quote_id is valid but the actor lacks quotes.view', function () {
    $actor = documentLayoutUserWith(['view']);
    Permission::findOrCreate('quotes.view');
    $layout = DocumentLayout::factory()->create(['module' => 'quotes', 'config' => dlpConfig()]);
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/document-layouts/{$layout->id}/preview", ['quote_id' => $quote->id])
        ->assertForbidden();
});

it('falls back to the most recent quote visible to the actor when quote_id is omitted', function () {
    $actor = documentLayoutUserWith(['view']);
    grantQuotesView($actor);
    $layout = DocumentLayout::factory()->create(['module' => 'quotes', 'config' => dlpConfig()]);
    Quote::factory()->create();
    $latest = Quote::factory()->create();
    $renderedDocx = captureDocxToPdfConversion();
    Sanctum::actingAs($actor);

    $this->postJson("/api/document-layouts/{$layout->id}/preview", [])->assertOk();

    $text = dlpDocumentText(dlpOpenZip($renderedDocx()));
    expect($text)->toContain($latest->code);
});

// ---------------------------------------------------------------------------
// AC-272 — authorization on the layout + invalid config
// ---------------------------------------------------------------------------

it('AC-272: 403 without document-layouts.view', function () {
    $actor = documentLayoutUserWith([]);
    $layout = DocumentLayout::factory()->create(['module' => 'quotes', 'config' => dlpConfig()]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/document-layouts/{$layout->id}/preview", [])->assertForbidden();
});

it('AC-272: 422 when the layouts persisted config no longer validates', function () {
    $actor = documentLayoutUserWith(['view']);
    $layout = DocumentLayout::factory()->create(['module' => 'quotes', 'config' => dlpConfig()]);
    DB::table('document_layouts')->where('id', $layout->id)->update(['config' => json_encode(['broken' => true])]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/document-layouts/{$layout->id}/preview", [])->assertStatus(422);
});

// ---------------------------------------------------------------------------
// AC-273 — filename contract
// ---------------------------------------------------------------------------

it('AC-273: Content-Disposition uses "{layout.code}-preview.pdf"', function () {
    $actor = documentLayoutUserWith(['view']);
    $layout = DocumentLayout::factory()->create(['module' => 'quotes', 'config' => dlpConfig()]);
    captureDocxToPdfConversion();
    Sanctum::actingAs($actor);

    $response = $this->postJson("/api/document-layouts/{$layout->id}/preview", [])->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain("{$layout->code}-preview.pdf")
        ->and($response->headers->get('Content-Type'))->toBe('application/pdf');
});
