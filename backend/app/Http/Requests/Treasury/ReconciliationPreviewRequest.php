<?php

declare(strict_types=1);

namespace App\Http\Requests\Treasury;

use Illuminate\Foundation\Http\FormRequest;

/** GET /api/financial-accounts/{financialAccount}/bank-reconciliations/preview (spec 0363): `treasury-movements.reconcile`. */
class ReconciliationPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('treasury-movements.reconcile') === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return ['statement_date' => ['required', 'date_format:Y-m-d']];
    }
}
