<?php

declare(strict_types=1);

namespace App\Http\Requests\FieldChangeRequests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /api/field-change-requests/for-record (spec 0078): feeds the
 * "richieste di modifica" section of a record's detail panel. Authorization
 * stays in the controller (viewer must hold `field-change-requests.viewAny`
 * OR be the requester of at least one request on the record, AC-038).
 */
class FieldChangeRequestForRecordRequest extends FormRequest
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
            'resource' => ['required', 'string'],
            'subject_id' => ['required', 'integer'],
        ];
    }

    public function resource(): string
    {
        return (string) $this->validated('resource');
    }

    public function subjectId(): int
    {
        return (int) $this->validated('subject_id');
    }
}
