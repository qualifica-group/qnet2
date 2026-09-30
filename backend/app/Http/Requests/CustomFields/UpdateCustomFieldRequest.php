<?php

namespace App\Http\Requests\CustomFields;

use App\CustomFields\CustomFieldEntityRegistry;
use App\CustomFields\FieldTypeRegistry;
use App\DataObjects\CustomFields\UpdateCustomFieldData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ValidatesFieldTypeDefinition;
use App\Models\CustomFieldDefinition;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/custom-fields/{customField} (spec
 * 0021 — ADMIN CRUD DEFINIZIONI, AC-019). Every field is `sometimes` to
 * support partial PATCH updates: `options`, when submitted, is a full-replace
 * of the option list; the entity_type/type/key-immutability guard (the
 * definition already has recorded values) is enforced by CustomFieldService,
 * not here (it needs a DB lookup on `custom_field_values`). The ENUM/RELATION
 * cross-field checks are the shared ValidatesFieldTypeDefinition concern
 * (also used by the `attributes` requests, spec 0017 alignment), overridden
 * below to fall back to the persisted `type` and to only fire when the
 * relevant key was actually submitted. Authorization is intentionally NOT
 * handled here (it stays in the controller via authorize('update',
 * $customField)). EnforcesFieldPermissions (spec 0004) additionally rejects
 * any submitted field the actor cannot edit on this specific model.
 */
class UpdateCustomFieldRequest extends FormRequest
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
        /** @var CustomFieldDefinition $customField */
        $customField = $this->route('customField');

        return [
            'entity_type' => ['sometimes', 'required', 'string', Rule::in($this->customFieldableEntityTypes())],
            'key' => [
                'sometimes', 'required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('custom_field_definitions', 'key')
                    ->where(fn ($query) => $query->where('entity_type', $this->input('entity_type', $customField->entity_type)))
                    ->ignore($customField->id),
            ],
            'type' => ['sometimes', 'required', 'string', Rule::in(app(FieldTypeRegistry::class)->all())],
            'label' => ['sometimes', 'required', 'string', 'max:191'],
            'description' => ['sometimes', 'nullable', 'string'],
            'help_text' => ['sometimes', 'nullable', 'string'],
            'placeholder' => ['sometimes', 'nullable', 'string', 'max:191'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:191'],
            'group' => ['sometimes', 'nullable', 'string', 'max:191'],
            'tab' => ['sometimes', 'nullable', 'string', 'max:191'],
            'sort_order' => ['sometimes', 'integer'],
            'config' => ['sometimes', 'nullable', 'array'],
            'validation' => ['sometimes', 'nullable', 'array'],
            'relation_target' => ['sometimes', 'nullable', 'array'],
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
     * The type this definition will have once this request is applied: the
     * submitted `type`, or the currently persisted one when not submitted.
     */
    protected function fieldTypeDefinitionType(): ?string
    {
        /** @var CustomFieldDefinition $customField */
        $customField = $this->route('customField');

        return $this->input('type', $customField->type);
    }

    /**
     * A submitted `options` full-replace on an ENUM field must never be
     * empty/carry duplicate values. When `options` is NOT submitted, the
     * persisted-count guard (relevant when `type` is changing TO enum without
     * a fresh options payload) is enforced by CustomFieldService, which needs
     * the model's current option count.
     */
    protected function shouldValidateOptions(): bool
    {
        return $this->has('options');
    }

    /**
     * A submitted `relation_target` on a RELATION field must be valid. When
     * NOT submitted, the persisted relation_target is assumed valid (it was
     * already validated when the field became a relation); CustomFieldService
     * still guards the case where `type` is changing TO relation without a
     * fresh relation_target.
     */
    protected function shouldValidateRelationTarget(): bool
    {
        return $this->has('relation_target');
    }

    /**
     * @return array<int, string>
     */
    private function customFieldableEntityTypes(): array
    {
        return array_column(app(CustomFieldEntityRegistry::class)->entities(), 'entity_type');
    }

    protected function shouldValidateTableConfig(): bool
    {
        return $this->has('config') || $this->has('type');
    }

    protected function tableConfigInput(): mixed
    {
        /** @var CustomFieldDefinition $customField */
        $customField = $this->route('customField');

        return $this->has('config') ? $this->input('config') : $customField->config;
    }

    protected function indexedInput(): bool
    {
        /** @var CustomFieldDefinition $customField */
        $customField = $this->route('customField');

        return $this->has('is_indexed') ? $this->boolean('is_indexed') : (bool) $customField->is_indexed;
    }

    protected function authorizationResource(): string
    {
        return 'custom-fields';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var CustomFieldDefinition $customField */
        $customField = $this->route('customField');

        return $customField;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateCustomFieldData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateCustomFieldData::fromValidated($validated);
    }
}
