<?php

namespace App\Http\Requests\AbsenceRequests;

use App\Models\AbsenceRequest;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Payload of POST /api/absence-requests/bulk-decision (spec 0304 D-17).
 */
class AbsenceRequestBulkDecisionRequest extends FormRequest
{
    public const int MAX_IDS = 100;

    public function authorize(): bool
    {
        /** @var User $actor */
        $actor = $this->user();

        return $actor->can('viewAny', AbsenceRequest::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_IDS],
            'ids.*' => ['integer', 'distinct'],
            'rejection_reason' => [Rule::requiredIf($this->input('action') === 'reject'), 'nullable', 'string', 'max:'.AbsenceRequestRejectRequest::REASON_MAX_LENGTH],
        ];
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_map('intval', $this->validated('ids'));
    }
}
