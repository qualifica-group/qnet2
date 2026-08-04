<?php

declare(strict_types=1);

namespace App\Http\Requests\FieldChangeRequests;

use App\DataObjects\FieldChangeRequests\CreateFieldChangeRequestData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/field-change-requests (spec 0078). Authorization is NOT handled
 * here (stays in the controller, mirroring every other module):
 * `field-change-requests.create` gates the endpoint wholesale, the per-field
 * business rules (protected? already writable? valid value? duplicate
 * pending?) are the Creator service's own responsibility.
 *
 * AC-015: the client may send `current_value`, `status`, `requested_by_id`
 * or `handled_by_id` in the body — `safe()->only()` below is the allow-list
 * that silently drops every one of them (D-6, server-computed only).
 * `requested_value` accepts ANY scalar/array shape (its TYPE is validated
 * downstream by CellValueValidator against the target column, not here).
 */
class StoreFieldChangeRequestRequest extends FormRequest
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
            'field' => ['required', 'string'],
            'requested_value' => ['required'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function toData(): CreateFieldChangeRequestData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->safe()->only(['resource', 'subject_id', 'field', 'requested_value', 'reason']);

        return new CreateFieldChangeRequestData(
            resource: (string) $validated['resource'],
            subjectId: (int) $validated['subject_id'],
            field: (string) $validated['field'],
            requestedValue: $validated['requested_value'],
            reason: $validated['reason'] ?? null,
        );
    }
}
