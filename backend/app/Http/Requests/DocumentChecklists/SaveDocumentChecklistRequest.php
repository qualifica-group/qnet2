<?php

declare(strict_types=1);

namespace App\Http\Requests\DocumentChecklists;

use App\DataObjects\DocumentChecklists\SaveDocumentChecklistData;
use App\Enums\DocumentChecklistFileType;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\DocumentChecklist;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates POST /api/document-checklists and PUT /api/document-checklists/{documentChecklist}
 * (spec 0403): both are a full replacement of the checklist and its items.
 * The limits live in config/archivio-smart.php and mirror the frontend schema.
 *
 * Authorization stays in the controller (Policy). A code change on a
 * code-locked checklist (D-4) is refused by EnforcesFieldPermissions: the
 * `code` field is read-only in DocumentChecklistsAuthorization once locked.
 */
class SaveDocumentChecklistRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        // Authorization handled in the controller via DocumentChecklistPolicy.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('code');

        if (is_string($code)) {
            $this->merge(['code' => strtoupper(trim($code))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $max = config('archivio-smart.max_length');
        $checklist = $this->checklist();

        return [
            'name' => ['required', 'string', 'max:'.$max['name']],
            'code' => [
                'required', 'string', 'max:'.$max['code'], 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('document_checklists', 'code')->whereNull('deleted_at')->ignore($checklist?->id),
            ],
            'description' => ['nullable', 'string', 'max:'.$max['description']],
            'is_active' => ['sometimes', 'boolean'],
            'category_id' => ['nullable', 'integer', Rule::exists('document_checklist_categories', 'id')->whereNull('deleted_at')],
            'items' => ['present', 'array', 'max:'.config('archivio-smart.max_items')],
            ...$this->itemRules($checklist, $max),
        ];
    }

    /**
     * @param  array<string, int>  $max
     * @return array<string, array<int, mixed>>
     */
    private function itemRules(?DocumentChecklist $checklist, array $max): array
    {
        $maxFiles = config('archivio-smart.max_files');

        return [
            'items.*.id' => $checklist === null
                ? ['nullable', 'prohibited']
                : [
                    'nullable', 'integer', 'distinct',
                    Rule::exists('document_checklist_items', 'id')->where('document_checklist_id', $checklist->id),
                ],
            'items.*.name' => ['required', 'string', 'max:'.$max['name']],
            'items.*.description' => ['nullable', 'string', 'max:'.$max['description']],
            'items.*.section' => ['nullable', 'string', 'max:'.$max['section']],
            'items.*.is_required' => ['required', 'boolean'],
            'items.*.allowed_file_types' => ['required', 'array', 'min:1', $this->withoutDuplicates(...)],
            'items.*.allowed_file_types.*' => ['required', Rule::enum(DocumentChecklistFileType::class)],
            'items.*.max_file_size_kb' => ['required', 'integer', 'between:1,'.config('archivio-smart.max_file_size_kb')],
            'items.*.min_files' => ['required', 'integer', 'between:1,'.$maxFiles],
            'items.*.max_files' => ['required', 'integer', 'between:1,'.$maxFiles, 'gte:items.*.min_files'],
            'items.*.validation_prompt' => ['nullable', 'string', 'max:'.$max['validation_prompt']],
            'items.*.max_age_days' => ['nullable', 'integer', 'between:1,'.config('archivio-smart.max_age_days')],
        ];
    }

    /**
     * The built-in `distinct` rule would compare file types across ALL items
     * (nested wildcard), so duplicates are checked per item here.
     */
    private function withoutDuplicates(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_array($value) && count($value) !== count(array_unique($value))) {
            $fail(__('document_checklists.duplicate_file_types'));
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        /** @var array<string, string> $labels */
        $labels = __('document_checklists.attributes');

        return collect($labels)
            ->mapWithKeys(fn (string $label, string $key): array => [
                str_starts_with($key, 'item_') ? 'items.*.'.substr($key, 5) : $key => $label,
            ])
            ->all();
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
        return $this->checklist();
    }

    protected function fieldNotEditableMessage(string $field): string
    {
        return $field === 'code' ? __('document_checklists.code_locked') : parent::fieldNotEditableMessage($field);
    }

    public function toData(): SaveDocumentChecklistData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return SaveDocumentChecklistData::fromValidated($validated);
    }

    private function checklist(): ?DocumentChecklist
    {
        $checklist = $this->route('documentChecklist');

        return $checklist instanceof DocumentChecklist ? $checklist : null;
    }
}
