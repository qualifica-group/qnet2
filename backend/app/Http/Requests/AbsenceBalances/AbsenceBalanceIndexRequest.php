<?php

namespace App\Http\Requests\AbsenceBalances;

use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /api/absence-balances (spec 0278). Module access is checked here; whose
 * balances the actor may read is decided by the controller.
 */
class AbsenceBalanceIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('absence-requests.viewAny');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'year' => ['sometimes', 'integer', 'min:2000', 'max:2100'],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
        ];
    }
}
