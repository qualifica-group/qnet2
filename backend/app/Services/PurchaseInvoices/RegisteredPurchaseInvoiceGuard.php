<?php

declare(strict_types=1);

namespace App\Services\PurchaseInvoices;

use App\Enums\PurchaseInvoiceType;
use App\Models\PurchaseInvoice;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Registration rules of a payable document (spec 0250, D-2, D-7): an invoice
 * (type=invoice) is frozen, its supplier number changes only with
 * `purchase-invoices.downgrade`, and the supplier number is unique per
 * supplier and year among the invoices. A payslip (spec 0431, D-7) has no number
 * and no editable header or lines: only its notes change.
 */
final class RegisteredPurchaseInvoiceGuard
{
    public const string DOWNGRADE_PERMISSION = 'purchase-invoices.downgrade';

    /** Header and lines of a payslip are not editable (422); the document is still deletable. */
    public function assertNotPayslip(PurchaseInvoice $document): void
    {
        if ($document->document_type === PurchaseInvoiceType::Payslip) {
            throw ValidationException::withMessages(['document_type' => [__('purchase_invoices.payslip_locked')]]);
        }
    }

    public function assertEditable(PurchaseInvoice $document): void
    {
        if ($document->isRegistered()) {
            abort(409, __('purchase_invoices.registered_locked'));
        }
    }

    /** A payslip never takes a number: the type must not flip to proforma/invoice (spec 0431, D-7). */
    public function assertNumberAllowed(PurchaseInvoice $document): void
    {
        if ($document->document_type === PurchaseInvoiceType::Payslip) {
            throw ValidationException::withMessages(['document_number' => [__('purchase_invoices.payslip_no_number')]]);
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
