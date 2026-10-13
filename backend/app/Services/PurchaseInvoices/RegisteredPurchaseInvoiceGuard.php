<?php

declare(strict_types=1);

namespace App\Services\PurchaseInvoices;

use App\Models\PurchaseInvoice;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Registration rules of a payable document (spec 0250, D-2, D-7): an invoice
 * (type=invoice) is frozen, its supplier number changes only with
 * `purchase-invoices.downgrade`, and the supplier number is unique per
 * supplier and year among the invoices.
 */
final class RegisteredPurchaseInvoiceGuard
{
    public const string DOWNGRADE_PERMISSION = 'purchase-invoices.downgrade';

    public function assertEditable(PurchaseInvoice $document): void
    {
        if ($document->isRegistered()) {
            abort(409, __('purchase_invoices.registered_locked'));
        }
    }

    /**
     * @param  string|null  $number  the incoming number, already normalized (empty = null)
     */
    public function assertNumberChangeAllowed(PurchaseInvoice $document, ?string $number, User $actor): void
    {
        if ($document->isRegistered() && $number !== $document->document_number && ! $actor->can(self::DOWNGRADE_PERMISSION)) {
            abort(403, __('purchase_invoices.downgrade_forbidden'));
        }
    }

    /**
     * @param  int|null  $ignoreDocumentId  the document being registered or edited
     *
     * @throws ValidationException
     */
    public function assertNumberUnique(int $supplierId, ?string $number, int $year, ?int $ignoreDocumentId = null): void
    {
        if ($number === null) {
            return;
        }

        $taken = PurchaseInvoice::query()
            ->where('supplier_id', $supplierId)
            ->where('document_number', $number)
            ->where('document_year', $year)
            ->when($ignoreDocumentId !== null, fn ($query) => $query->whereKeyNot($ignoreDocumentId))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['document_number' => [__('purchase_invoices.duplicate_number')]]);
        }
    }
}
