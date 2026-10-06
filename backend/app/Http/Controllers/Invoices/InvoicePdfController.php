<?php

declare(strict_types=1);

namespace App\Http\Controllers\Invoices;

use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Invoices\InvoicePdfRequest;
use App\Models\Invoice;
use App\Models\User;
use App\Services\DocumentLayouts\Rendering\Exceptions\InvalidDocumentLayoutConfigException;
use App\Services\Invoices\InvoiceLayoutUnavailableException;
use App\Services\Invoices\InvoicePdfRenderer;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * GET /api/invoices/{invoice}/pdf — the invoice/proforma as a `.pdf` (spec 0195
 * D-8): a pure READ gated by `invoices.view` (in the FormRequest), generated
 * in-request and streamed back, nothing persisted.
 */
class InvoicePdfController extends BaseApiController
{
    private const string CONTENT_TYPE = 'application/pdf';

    public function __construct(private readonly InvoicePdfRenderer $renderer) {}

    public function __invoke(InvoicePdfRequest $request, Invoice $invoice): StreamedResponse|JsonResponse
    {
        try {
            /** @var User $actor */
            $actor = $request->user();
            $pdf = $this->renderer->render($invoice, $actor, $request->layout());

            return response()->streamDownload(
                static function () use ($pdf): void {
                    echo $pdf['bytes'];
                },
                $pdf['filename'],
                ['Content-Type' => self::CONTENT_TYPE],
            );
        } catch (InvoiceLayoutUnavailableException $exception) {
            return $this->fail($exception->getMessage(), HttpStatusEnum::UNPROCESSABLE_ENTITY->value);
        } catch (InvalidDocumentLayoutConfigException $exception) {
            return $this->fail($exception->getMessage(), HttpStatusEnum::UNPROCESSABLE_ENTITY->value, $exception->errors());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['invoice' => $invoice->id]);
        }
    }
}
