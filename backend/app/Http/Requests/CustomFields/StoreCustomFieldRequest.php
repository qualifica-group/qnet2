<?php

namespace App\Http\Requests\CustomFields;

use App\CustomFields\CustomFieldEntityRegistry;
use App\CustomFields\FieldTypeRegistry;
use App\DataObjects\CustomFields\CreateCustomFieldData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ValidatesFieldTypeDefinition;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/custom-fields (spec 0021 — ADMIN CRUD
 * DEFINIZIONI, AC-018). Authorization is intentionally NOT handled here (it
 * stays in the controller via authorize('create', CustomFieldDefinition::class)).
 * `options` is required/non-empty ONLY when type=enum; `relation_target` is
 * required/valid ONLY when type=relation — enforced by the shared
 * ValidatesFieldTypeDefinition concern (also used by the `attributes`
 * requests, spec 0017 alignment). EnforcesFieldPermissions (spec 0004)
 * additionally rejects any submitted field the actor cannot edit (create
 * context, model = null).
 */
class StoreCustomFieldRequest extends FormRequest
{
    use EnforcesFieldPermissions, ValidatesFieldTypeDefinition;

    public function authorize(): bool
    {
        // Authorization handled in the controller via CustomFieldDefinitionPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'entity_type' => ['required', 'string', Rule::in($this->customFieldableEntityTypes())],
            'key' => [
                'required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('custom_field_definitions', 'key')
                    ->where(fn ($query) => $query->where('entity_type', $this->input('entity_type'))),
            ],
            'type' => ['required', 'string', Rule::in(app(FieldTypeRegistry::class)->all())],
            'label' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'help_text' => ['nullable', 'string'],
            'placeholder' => ['nullable', 'string', 'max:191'],
            'icon' => ['nullable', 'string', 'max:191'],
            'group' => ['nullable', 'string', 'max:191'],
            'tab' => ['nullable', 'string', 'max:191'],
            'sort_order' => ['sometimes', 'integer'],
            'config' => ['nullable', 'array'],
            'validation' => ['nullable', 'array'],
            'relation_target' => ['nullable', 'array'],
            'is_indexed' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'options' => ['sometimes', 'array'],
            'options.*.value' => ['required', 'string', 'max:191'],
            'options.*.label' => ['required', 'string', 'max:191'],
            'options.*.color' => ['nullable', 'string', 'max:32'],
            'options.*.icon' => ['nullable', 'string', 'max:191'],
            'options.*.sort_order' => ['sometimes', 'integer'],
            'options.*.is_default' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
            $this->validateEnumOptions($validator);
            $this->validateRelationTarget($validator);
            $this->validateTableConfig($validator);
            $this->rejectIndexedTable($validator);
        });
    }

    /**
     * @return array<int, string>
     */
    private function customFieldableEntityTypes(): array
    {
        return array_column(app(CustomFieldEntityRegistry::class)->entities(), 'entity_type');
    }

    protected function authorizationResource(): string
    {
        return 'custom-fields';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateCustomFieldData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateCustomFieldData::fromValidated($validated);
    }
}
