<?php

declare(strict_types=1);

namespace App\Http\Requests\DocumentChecklistCategories;

use App\DataObjects\DocumentChecklistCategories\SaveDocumentChecklistCategoryData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\DocumentChecklistCategory;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates POST /api/document-checklist-categories and
 * PUT /api/document-checklist-categories/{documentChecklistCategory} (spec 0410):
 * both are a full replacement. Limits live in config/archivio-smart.php and
 * mirror the frontend schema. Authorization stays in the controller (Policy).
 */
class SaveDocumentChecklistCategoryRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        // Authorization handled in the controller via DocumentChecklistCategoryPolicy.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        if (is_string($name)) {
            $this->merge(['name' => trim($name)]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $max = config('archivio-smart.max_length');

        return [
            'name' => ['required', 'string', 'max:'.$max['name'], $this->uniqueAmongActive(...)],
            'description' => ['nullable', 'string', 'max:'.$max['description']],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:'.config('archivio-smart.max_category_sort_order')],
        ];
    }

    /**
     * Case-insensitive uniqueness among the non-deleted categories (D-5). The
     * column is fixed and the value is a bound parameter.
     */
    private function uniqueAmongActive(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $taken = DocumentChecklistCategory::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($value)])
            ->when($this->category() !== null, fn ($query) => $query->whereKeyNot($this->category()?->id))
            ->exists();

        if ($taken) {
            $fail(__('document_checklists.category_name_taken'));
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        /** @var array<string, string> $labels */
        $labels = __('document_checklists.attributes');

        return $labels;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'document-checklist-categories';
    }

    protected function authorizationModel(): ?Model
    {
        return $this->category();
    }

    public function toData(): SaveDocumentChecklistCategoryData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return SaveDocumentChecklistCategoryData::fromValidated($validated);
    }

    private function category(): ?DocumentChecklistCategory
    {
        $category = $this->route('documentChecklistCategory');

        return $category instanceof DocumentChecklistCategory ? $category : null;
    }
}
