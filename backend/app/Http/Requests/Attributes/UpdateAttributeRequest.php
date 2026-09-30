<?php

namespace App\Http\Requests\Attributes;

use App\CustomFields\FieldTypeRegistry;
use App\DataObjects\Attributes\UpdateAttributeData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ValidatesFieldTypeDefinition;
use App\Models\Attribute;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/attributes/{attribute} (spec
 * 0017, aligned to the custom fields' presentation shape — spec 0021).
 * Every field is `sometimes` to support partial PATCH updates: `options`,
 * when submitted, is a full-replace of the option list. The ENUM/RELATION
 * cross-field checks are the shared ValidatesFieldTypeDefinition concern
 * (also used by the `custom-fields` requests), overridden below to fall back
 * to the persisted `type` and to only fire when the relevant key was
 * actually submitted. Authorization is intentionally NOT handled here (it
 * stays in the controller via authorize('update', $attribute)).
 * EnforcesFieldPermissions (spec 0004) additionally rejects any submitted
 * field the actor cannot edit on this specific model.
 */
class UpdateAttributeRequest extends FormRequest
{
    use EnforcesFieldPermissions, ValidatesFieldTypeDefinition;

    public function authorize(): bool
    {
        // Authorization handled in the controller via AttributePolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Attribute $attribute */
        $attribute = $this->route('attribute');

        return [
            'code' => ['sometimes', 'required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/', Rule::unique('attributes', 'code')->ignore($attribute->id)],
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'type' => ['sometimes', 'required', 'string', Rule::in(app(FieldTypeRegistry::class)->all())],
            'description' => ['sometimes', 'nullable', 'string'],
            'help_text' => ['sometimes', 'nullable', 'string'],
            'placeholder' => ['sometimes', 'nullable', 'string', 'max:191'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:191'],
            'config' => ['sometimes', 'nullable', 'array'],
            'relation_target' => ['sometimes', 'nullable', 'array'],
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
        });
    }

    /**
     * The type this attribute will have once this request is applied: the
     * submitted `type`, or the currently persisted one when not submitted.
     */
    protected function fieldTypeDefinitionType(): ?string
    {
        /** @var Attribute $attribute */
        $attribute = $this->route('attribute');

        return $this->input('type', $attribute->type);
    }

    /**
     * A submitted `options` full-replace on an ENUM attribute must never be
     * empty/carry duplicate values. When `options` is NOT submitted, the
     * persisted-count guard (relevant when `type` is changing TO enum
     * without a fresh options payload) is enforced by AttributeService,
     * which needs the model's current option count.
     */
    protected function shouldValidateOptions(): bool
    {
        return $this->has('options');
    }

    /**
     * A submitted `relation_target` on a RELATION attribute must be valid.
     * When NOT submitted, the persisted relation_target is assumed valid (it
     * was already validated when the attribute became a relation);
     * AttributeService still guards the case where `type` is changing TO
     * relation without a fresh relation_target.
     */
    protected function shouldValidateRelationTarget(): bool
    {
        return $this->has('relation_target');
    }

    protected function shouldValidateTableConfig(): bool
    {
        return $this->has('config') || $this->has('type');
    }

    protected function tableConfigInput(): mixed
    {
        /** @var Attribute $attribute */
        $attribute = $this->route('attribute');

        return $this->has('config') ? $this->input('config') : $attribute->config;
    }

    protected function authorizationResource(): string
    {
        return 'attributes';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var Attribute $attribute */
        $attribute = $this->route('attribute');

        return $attribute;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateAttributeData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateAttributeData::fromValidated($validated);
    }
}
