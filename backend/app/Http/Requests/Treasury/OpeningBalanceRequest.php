<?php

declare(strict_types=1);

namespace App\Http\Requests\Treasury;

use Illuminate\Foundation\Http\FormRequest;

/** PATCH /api/financial-accounts/{financialAccount}/opening-balance (spec 0355, D-1). */
class OpeningBalanceRequest extends FormRequest
{
    public const string MAX_AMOUNT = '999999999.99';

    public function authorize(): bool
    {
        return $this->user()?->can('manageOpeningBalance', $this->route('financialAccount')) === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'opening_balance' => ['required', 'numeric', 'decimal:0,2', 'between:-'.self::MAX_AMOUNT.','.self::MAX_AMOUNT],
            'opening_balance_date' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
