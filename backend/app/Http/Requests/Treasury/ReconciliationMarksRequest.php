<?php

declare(strict_types=1);

namespace App\Http\Requests\Treasury;

use Illuminate\Foundation\Http\FormRequest;

/** POST /api/financial-accounts/{financialAccount}/reconciliation-marks (spec 0363): `treasury-movements.reconcile`. */
class ReconciliationMarksRequest extends FormRequest
{
    public const int MAX_KEYS = 500;

    public function authorize(): bool
    {
        return $this->user()?->can('treasury-movements.reconcile') === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'keys' => ['required', 'array', 'min:1', 'max:'.self::MAX_KEYS],
            'keys.*' => ['required', 'string', 'max:40', 'distinct'],
            'reconciled' => ['required', 'boolean'],
        ];
    }
}
