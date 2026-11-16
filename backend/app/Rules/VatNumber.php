<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Fiscal\ForeignFiscalCode;
use App\Support\Fiscal\ItalianVatNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates a VAT number: an Italian one (eleven digits with a valid control
 * digit, optional `IT` prefix tolerated) or a foreign one written with its
 * country prefix. A blank value passes — whether the field is required is the
 * FormRequest's call.
 */
final class VatNumber implements ValidationRule
{
    /**
     * @param  Closure(string): void  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        if (! ItalianVatNumber::isValid($value) && ! ForeignFiscalCode::isValid($value)) {
            $fail(__('The VAT number is not valid. A foreign one needs its country prefix (e.g. DE123456789).'));
        }
    }
}
