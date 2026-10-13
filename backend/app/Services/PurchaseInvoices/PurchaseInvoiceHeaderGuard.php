<?php

declare(strict_types=1);

namespace App\Services\PurchaseInvoices;

use App\Enums\FinancialAccountType;
use App\Models\FinancialAccount;
use App\Models\PurchaseInvoice;
use Illuminate\Validation\ValidationException;

/**
 * Header rules of a payable document that need the database (spec 0250): the
 * parties of a stored document are fixed and the bank account is the company's.
 */
final class PurchaseInvoiceHeaderGuard
{
    /**
     * @param  array<string, mixed>  $data  write payload
     *
     * @throws ValidationException
     */
    public function assertPartiesUnchanged(PurchaseInvoice $document, array $data): void
    {
        $errors = [];

        if ((int) $data['supplier_id'] !== $document->supplier_id) {
            $errors['supplier_id'] = [__('purchase_invoices.supplier_locked')];
        }

        if ((int) $data['company_id'] !== $document->company_id) {
            $errors['company_id'] = [__('purchase_invoices.company_locked')];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $data  write payload
     *
     * @throws ValidationException
     */
    public function assertBankBelongsToCompany(array $data): void
    {
        if (empty($data['financial_account_id'])) {
            return;
        }

        $valid = FinancialAccount::query()
            ->whereKey($data['financial_account_id'])
            ->where('type', FinancialAccountType::BankAccount->value)
            ->where('company_id', $data['company_id'])
            ->exists();

        if (! $valid) {
            throw ValidationException::withMessages(['financial_account_id' => [__('purchase_invoices.bank_account_company')]]);
        }
    }
}
