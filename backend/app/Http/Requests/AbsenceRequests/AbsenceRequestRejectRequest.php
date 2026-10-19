<?php

namespace App\Http\Requests\AbsenceRequests;

use App\Services\Absences\Requests\AbsenceRequestVisibility;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload of POST /api/absence-requests/{absenceRequest}/reject (spec 0278):
 * the reason is mandatory. A request the actor cannot see is a 404.
 */
class AbsenceRequestRejectRequest extends FormRequest
{
    public const int REASON_MAX_LENGTH = 1000;

    public function authorize(): bool
    {
        app(AbsenceRequestVisibility::class)->assertCanView($this->user(), $this->route('absenceRequest'));

        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'max:'.self::REASON_MAX_LENGTH],
        ];
    }

    public function reason(): string
    {
        return (string) $this->validated('rejection_reason');
    }
}
