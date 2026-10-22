<?php

namespace App\Http\Requests\AbsenceRequests;

/**
 * Payload of POST /api/absence-requests/preview (spec 0304): the write payload
 * of a request without `submit`, plus the draft being edited. Same authorization
 * and rules as the creation, nothing is written.
 */
class AbsenceRequestPreviewRequest extends AbsenceRequestWriteRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['submit']);
        $rules['request_id'] = ['nullable', 'integer'];

        return $rules;
    }

    public function draftId(): ?int
    {
        $id = $this->validated('request_id');

        return $id === null ? null : (int) $id;
    }
}
