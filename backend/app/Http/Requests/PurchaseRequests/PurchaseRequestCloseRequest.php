<?php

namespace App\Http\Requests\PurchaseRequests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload of POST /api/purchase-requests/{purchaseRequest}/close (spec 0208).
 * The reason is mandatory only for a forced closure, which depends on the
 * stored lines and is therefore checked by PurchaseRequestClosureService.
 */
class PurchaseRequestCloseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:'.PurchaseRequestLineStatusRequest::REASON_MAX_LENGTH],
        ];
    }

    public function reason(): ?string
    {
        return $this->validated('reason');
    }
}
