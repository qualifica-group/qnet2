<?php

namespace App\Http\Requests\PurchaseRequests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query of GET /api/purchase-requests/approver-options (spec 0245, D-5). Only
 * who may create or edit a request needs the list of eligible approvers.
 */
class PurchaseRequestApproverOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && ($user->can('purchase-requests.create') || $user->can('purchase-requests.update'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'requester_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'business_function_id' => ['nullable', 'integer', Rule::exists('business_functions', 'id')],
        ];
    }

    public function requesterId(): int
    {
        return (int) $this->validated('requester_id');
    }

    public function businessFunctionId(): ?int
    {
        $id = $this->validated('business_function_id');

        return $id === null ? null : (int) $id;
    }
}
