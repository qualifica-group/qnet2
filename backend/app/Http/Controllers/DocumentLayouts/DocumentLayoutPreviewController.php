<?php

declare(strict_types=1);

namespace App\Http\Controllers\DocumentLayouts;

use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\DocumentLayouts\DocumentLayoutPreviewRequest;
use App\Models\DocumentLayout;
use App\Models\Quote;
use App\Models\User;
use App\Services\DocumentLayouts\Rendering\Exceptions\InvalidDocumentLayoutConfigException;
use App\Services\DocumentLayouts\Rendering\QuoteDocumentGenerator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * POST /api/document-layouts/{documentLayout}/preview — a real `.docx`
 * rendered from THIS layout's config (spec 0070; 0069 D-8 promised a
 * high-fidelity check beyond the editor's HTML approximation), gated by
 * `document-layouts.view`.
 *
 * The Quote rendered against is resolved in three steps: the requested
 * `quote_id` when given (404 if unknown, 403 if the actor lacks
 * `quotes.view` on it — never a silent fallback, "e' solo un'anteprima" is
 * not a shortcut for authorization); otherwise the most recent quote visible
 * to the actor; otherwise a deterministic, NEVER-persisted in-memory sample
 * Quote (AC-270: the endpoint must answer 200 even on a database with zero
 * quotes). The sample's foreign keys are all unset, so every relation
 * VariableResolver/ProductsTableRenderer reads resolves to null/empty by
 * construction — rendered exactly like a real quote with no data.
 */
class DocumentLayoutPreviewController extends BaseApiController
{
    use AuthorizesRequests;

    private const string CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    private const string SAMPLE_CODE = 'QUO-SAMPLE';

    private const string SAMPLE_TITLE = 'Sample quote';

    public function __construct(private readonly QuoteDocumentGenerator $generator) {}

    public function __invoke(DocumentLayoutPreviewRequest $request, DocumentLayout $documentLayout): StreamedResponse|JsonResponse
    {
        try {
            $this->authorize('view', $documentLayout);

            /** @var User $actor */
            $actor = $request->user();
            $quote = $this->resolveQuote($request, $actor);

            $binary = $this->generator->generate($quote, $documentLayout, $actor);

            return $this->streamDocx($binary, "{$documentLayout->code}-preview.docx");
        } catch (InvalidDocumentLayoutConfigException $exception) {
            return $this->fail($exception->getMessage(), HttpStatusEnum::UNPROCESSABLE_ENTITY->value, $exception->errors());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['documentLayout' => $documentLayout->id]);
        }
    }

    private function resolveQuote(DocumentLayoutPreviewRequest $request, User $actor): Quote
    {
        $quoteId = $request->quoteId();

        if ($quoteId !== null) {
            $quote = Quote::query()->findOrFail($quoteId);
            $this->authorize('view', $quote);

            return $quote;
        }

        return $this->mostRecentVisibleQuote($actor) ?? $this->sampleQuote();
    }

    private function mostRecentVisibleQuote(User $actor): ?Quote
    {
        if (! $actor->can('quotes.view')) {
            return null;
        }

        return Quote::query()->latest('id')->first();
    }

    private function sampleQuote(): Quote
    {
        $quote = new Quote;
        $quote->code = self::SAMPLE_CODE;
        $quote->title = self::SAMPLE_TITLE;

        return $quote;
    }

    private function streamDocx(string $binary, string $filename): StreamedResponse
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
