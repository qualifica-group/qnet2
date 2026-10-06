<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use RuntimeException;

/**
 * Thrown when no document layout can render an invoice (spec 0195 D-8): no
 * explicit layout and no active default for the `invoices` module. Callers map
 * it to a 422.
 */
final class InvoiceLayoutUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('document_layouts.invoice_no_layout_available'));
    }
}
