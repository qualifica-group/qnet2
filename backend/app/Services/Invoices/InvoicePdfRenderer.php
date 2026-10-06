<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Models\DocumentLayout;
use App\Models\Invoice;
use App\Models\User;
use App\Services\DocumentLayouts\InvoiceDocumentLayoutResolver;
use App\Services\DocumentLayouts\Rendering\DocumentGenerator;
use App\Services\DocumentLayouts\Rendering\DocxToPdfConverter;
use App\Services\DocumentLayouts\Rendering\Invoices\InvoiceRenderSubject;

/**
 * Renders an Invoice to PDF (spec 0195 D-8): layout = the given override, else the
 * invoice's saved one, else the `invoices` active default (spec 0196). Shared by the GET pdf endpoint and the
 * email attachment flow; nothing is persisted.
 */
final class InvoicePdfRenderer
{
    public function __construct(
        private readonly DocumentGenerator $generator,
        private readonly DocxToPdfConverter $pdfConverter,
        private readonly InvoiceDocumentLayoutResolver $layouts,
    ) {}

    /**
     * @return array{filename: string, bytes: string}
     *
     * @throws InvoiceLayoutUnavailableException when no layout is available
     */
    public function render(Invoice $invoice, User $actor, ?DocumentLayout $override = null): array
    {
        // Step 1: pick the layout.
        $layout = $override ?? $this->layouts->resolve($invoice) ?? throw new InvoiceLayoutUnavailableException;

        // Step 2: docx -> pdf.
        $docx = $this->generator->generate(InvoiceRenderSubject::for($invoice), $layout, $actor);

        return ['filename' => $this->filename($invoice), 'bytes' => $this->pdfConverter->convert($docx)];
    }

    private function filename(Invoice $invoice): string
    {
        return "{$invoice->type->value}_{$invoice->number}_{$invoice->year}.pdf";
    }
}
