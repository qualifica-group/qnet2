<?php

namespace App\Exceptions\Invoices;

use RuntimeException;

/**
 * A payment method whose installment configuration cannot produce a schedule
 * (spec 0194, D-11: `vat_first` needs at least 2 installments). The service
 * layer turns it into a 422 on `payment_method_id`.
 */
final class InvalidInstallmentConfigurationException extends RuntimeException
{
    public static function vatFirstNeedsTwoInstallments(): self
    {
        return new self('The "vat_first" VAT allocation requires at least 2 installments.');
    }
}
