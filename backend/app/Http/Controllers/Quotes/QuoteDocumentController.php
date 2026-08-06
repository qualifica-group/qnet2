<?php

declare(strict_types=1);

namespace App\Http\Controllers\Quotes;

use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Models\Quote;
use App\Models\User;
use App\Services\DocumentLayouts\QuoteDocumentLayoutResolver;
use App\Services\DocumentLayouts\Rendering\DocxToPdfConverter;
use App\Services\DocumentLayouts\Rendering\Exceptions\InvalidDocumentLayoutConfigException;
use App\Services\DocumentLayouts\Rendering\QuoteDocumentGenerator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * POST /api/quotes/{quote}/document — generate the quote's `.pdf` (spec
 * 0070, D-2/D-3/D-4): a pure READ (gated by `quotes.view`, never `update`),
 * generated synchronously in-request and streamed back with no persisted
 * artifact and no tracking row — mirrors
 * App\Http\Controllers\Import\ImportController::template()'s in-request
 * `streamDownload`, not the async ExportRun pipeline (a quote's line count is
 * bounded by ValidatesQuoteLines, so the cost is bounded by construction).
 *
 * The document is rendered as OOXML by QuoteDocumentGenerator and converted by
 * DocxToPdfConverter: PDF is the DELIVERED format, DOCX stays the internal
 * rendering format.
 *
 * The layout used is resolved by QuoteDocumentLayoutResolver: the quote's own
 * `layout_id` when set (even if that layout has since been deactivated),
 * otherwise the `quotes` module's current active default. Neither existing
 * is a 422 (`quotes.no_layout_available`), never a 500 or an empty file. A
 * layout whose persisted `config` no longer validates (manually corrupted at
 * DB level) also 422s, via InvalidDocumentLayoutConfigException.
 *
 * Invokable, single action: no other verb exists on this route.
 */
class QuoteDocumentController extends BaseApiController
{
    use AuthorizesRequests;

    private const string CONTENT_TYPE = 'application/pdf';

    public function __construct(
        private readonly QuoteDocumentLayoutResolver $layoutResolver,
        private readonly QuoteDocumentGenerator $generator,
        private readonly DocxToPdfConverter $pdfConverter,
    ) {}

    public function __invoke(Request $request, Quote $quote): StreamedResponse|JsonResponse
    {
        try {
            $this->authorize('view', $quote);

            /** @var User $actor */
            $actor = $request->user();
            $layout = $this->layoutResolver->resolve($quote);

            if ($layout === null) {
                return $this->fail(__('quotes.no_layout_available'), HttpStatusEnum::UNPROCESSABLE_ENTITY->value);
            }

            $docx = $this->generator->generate($quote, $layout, $actor);

            return $this->streamPdf($this->pdfConverter->convert($docx), "{$quote->code}.pdf");
        } catch (InvalidDocumentLayoutConfigException $exception) {
            return $this->fail($exception->getMessage(), HttpStatusEnum::UNPROCESSABLE_ENTITY->value, $exception->errors());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['quote' => $quote->id]);
        }
    }

    private function streamPdf(string $binary, string $filename): StreamedResponse
    {
        return response()->streamDownload(
            static function () use ($binary): void {
                echo $binary;
            },
            $filename,
            ['Content-Type' => self::CONTENT_TYPE],
        );
    }
}
