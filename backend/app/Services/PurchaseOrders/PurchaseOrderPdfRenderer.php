<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrders;

use App\Models\DocumentLayout;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\DocumentLayouts\PurchaseOrderDocumentLayoutResolver;
use App\Services\DocumentLayouts\Rendering\DocumentGenerator;
use App\Services\DocumentLayouts\Rendering\DocxToPdfConverter;
use App\Services\DocumentLayouts\Rendering\PurchaseOrders\PurchaseOrderRenderSubject;

/**
 * Renders a PurchaseOrder to PDF (spec 0210): layout = the given override,
 * else the `purchase_orders` active default. Shared by the GET pdf endpoint
 * and the email attachment flow; nothing is persisted.
 */
final class PurchaseOrderPdfRenderer
{
    public function __construct(
        private readonly DocumentGenerator $generator,
        private readonly DocxToPdfConverter $pdfConverter,
        private readonly PurchaseOrderDocumentLayoutResolver $layouts,
    ) {}

    /**
     * @return array{filename: string, bytes: string}
     *
     * @throws PurchaseOrderLayoutUnavailableException when no layout is available
     */
    public function render(PurchaseOrder $order, User $actor, ?DocumentLayout $override = null): array
    {
        // Step 1: pick the layout.
        $layout = $override ?? $this->layouts->resolve() ?? throw new PurchaseOrderLayoutUnavailableException;

        // Step 2: docx -> pdf.
        $docx = $this->generator->generate(PurchaseOrderRenderSubject::for($order), $layout, $actor);

        return ['filename' => "ODA-{$order->number}-{$order->year}.pdf", 'bytes' => $this->pdfConverter->convert($docx)];
    }
}
