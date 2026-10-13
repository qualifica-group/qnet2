<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts;

use App\Enums\DocumentLayoutModule;
use App\Models\DocumentLayout;

/**
 * Resolves WHICH DocumentLayout a purchase order's PDF is generated against
 * (spec 0210): the `purchase_orders` module's active default. Orders carry no
 * layout of their own. Returns null when none exists; the caller maps that to
 * a 422.
 */
final class PurchaseOrderDocumentLayoutResolver
{
    public function resolve(): ?DocumentLayout
    {
        return DocumentLayout::activeDefaultFor(DocumentLayoutModule::PurchaseOrders);
    }
}
