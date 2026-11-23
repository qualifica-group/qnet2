<?php

declare(strict_types=1);

namespace App\Http\Requests\DocumentChecklists;

use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\DocumentChecklist;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates PATCH /api/document-checklists/{documentChecklist}/activation (spec 0403).
 */
class SetDocumentChecklistActivationRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        // Authorization handled in the controller via DocumentChecklistPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['is_active' => __('document_checklists.attributes.is_active')];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'document-checklists';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var DocumentChecklist $checklist */
        $checklist = $this->route('documentChecklist');

        return $checklist;
    }

    public function isActive(): bool
    {
        return $this->boolean('is_active');
    }
}
