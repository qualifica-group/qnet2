<?php

declare(strict_types=1);

use App\Models\DocumentLayout;
use App\Models\Quote;
use App\Models\User;
use App\Services\DocumentLayouts\Rendering\DocxToPdfConverter;
use App\Services\DocumentLayouts\Rendering\Exceptions\DocumentConversionException;
use App\Services\DocumentLayouts\Rendering\QuoteDocumentGenerator;
use Database\Factories\DocumentLayoutFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * The DOCX-to-PDF conversion, exercised against the REAL headless LibreOffice
 * binary — the one place in the suite that does, so that
 * `config('documents.pdf.binary')` being absent or broken fails here loudly
 * instead of surfacing as a 500 in production. Every other document test uses
 * the double from `captureDocxToPdfConversion()`.
 *
 * A red run here means LibreOffice is missing or unusable on this machine, not
 * that the rendering pipeline regressed: it is a deployment requirement of the
 * feature (config/documents.php).
 */
uses(RefreshDatabase::class);

function pdfActorWithQuotesView(): User
{
    Permission::findOrCreate('quotes.view');

    return tap(User::factory()->create())->givePermissionTo('quotes.view');
}

/**
 * A real `.docx` rendered through the ordinary pipeline: converting a
 * hand-written stub would prove nothing about the documents we actually ship.
 */
function pdfSourceDocx(): string
{
    $layout = DocumentLayout::factory()->create([
        'module' => 'quotes',
        'config' => DocumentLayoutFactory::minimalConfig(),
    ]);
    $quote = Quote::factory()->create(['layout_id' => $layout->id]);

    return app(QuoteDocumentGenerator::class)->generate($quote, $layout, User::factory()->create());
}

it('converts a rendered docx into a single-page PDF', function () {
    $pdf = app(DocxToPdfConverter::class)->convert(pdfSourceDocx());

    expect($pdf)->toStartWith('%PDF-')
        ->and(strlen($pdf))->toBeGreaterThan(1000)
        // The trailer is the evidence the file was closed, not truncated.
        ->and($pdf)->toContain('%%EOF');
});

it('leaves no workspace behind in the system temp dir', function () {
    $before = glob(sys_get_temp_dir().'/docx-pdf-*');

    app(DocxToPdfConverter::class)->convert(pdfSourceDocx());

    expect(glob(sys_get_temp_dir().'/docx-pdf-*'))->toBe($before);
});

it('fails loudly when the configured binary does not exist', function () {
    config()->set('documents.pdf.binary', '/nonexistent/soffice');

    expect(fn () => app(DocxToPdfConverter::class)->convert(pdfSourceDocx()))
        ->toThrow(DocumentConversionException::class);
});

it('answers 500 with the generic envelope, never a broken download, when conversion fails', function () {
    config()->set('documents.pdf.binary', '/nonexistent/soffice');
    // Production behaviour: the LibreOffice stderr belongs in the log, not in
    // the response body (backend.md §2).
    config()->set('app.debug', false);

    $actor = pdfActorWithQuotesView();
    $layout = DocumentLayout::factory()->create([
        'module' => 'quotes',
        'config' => DocumentLayoutFactory::minimalConfig(),
    ]);
    $quote = Quote::factory()->create(['layout_id' => $layout->id]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/quotes/{$quote->id}/document")
        ->assertStatus(500)
        ->assertJson(['success' => false, 'message' => __('An unexpected error occurred.')]);
});

it('delivers a real PDF end-to-end through the endpoint', function () {
    $actor = pdfActorWithQuotesView();
    $layout = DocumentLayout::factory()->create([
        'module' => 'quotes',
        'config' => DocumentLayoutFactory::minimalConfig(),
    ]);
    $quote = Quote::factory()->create(['layout_id' => $layout->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson("/api/quotes/{$quote->id}/document")->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toContain("{$quote->code}.pdf")
        ->and($response->streamedContent())->toStartWith('%PDF-');
});
