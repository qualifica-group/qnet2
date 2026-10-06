<?php

declare(strict_types=1);

namespace App\Http\Controllers\InvoiceEmails;

use App\Http\Controllers\OwnerEmails\OwnerEmailComposeContextController;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/invoices/{invoice}/emails/compose-context (spec 0195, D-12): the
 * invoice binding of OwnerEmailComposeContextController.
 */
class InvoiceEmailComposeContextController extends OwnerEmailComposeContextController
{
    public function __invoke(Request $request, Invoice $invoice): JsonResponse
    {
        return $this->composeContext($request, $invoice);
    }
}
