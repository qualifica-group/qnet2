<?php

declare(strict_types=1);

namespace App\Http\Requests\FieldChangeRequests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared by POST /api/field-change-requests/{id}/approve and .../reject
 * (spec 0078): both accept only an optional free-text `note` (handling_note,
 * D-3). Authorization (`field-change-requests.manage`) stays in the
 * controller.
 */
class HandleFieldChangeRequestRequest extends FormRequest
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
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function note(): ?string
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->safe()->only(['note']);

        return $validated['note'] ?? null;
    }
}
