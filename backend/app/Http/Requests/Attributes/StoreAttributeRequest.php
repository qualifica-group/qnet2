<?php

namespace App\Http\Requests\Attributes;

use App\CustomFields\FieldTypeRegistry;
use App\DataObjects\Attributes\CreateAttributeData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ValidatesFieldTypeDefinition;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/attributes (spec 0017, aligned to the
 * custom fields' presentation shape — spec 0021, AC-003). Authorization is
 * intentionally NOT handled here (it stays in the controller via
 * authorize('create', Attribute::class)). `options` is required and
 * non-empty ONLY when type=enum; `relation_target` is required/valid ONLY
 * when type=relation — enforced by the shared ValidatesFieldTypeDefinition
 * concern (also used by the `custom-fields` requests). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * (create context, model = null).
 */
class StoreAttributeRequest extends FormRequest
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
        return [
            'code' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/', Rule::unique('attributes', 'code')],
            'name' => ['required', 'string', 'max:191'],
            'type' => ['required', 'string', Rule::in(app(FieldTypeRegistry::class)->all())],
            'description' => ['nullable', 'string'],
            'help_text' => ['nullable', 'string'],
            'placeholder' => ['nullable', 'string', 'max:191'],
            'icon' => ['nullable', 'string', 'max:191'],
            'config' => ['nullable', 'array'],
            'relation_target' => ['nullable', 'array'],
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

    protected function authorizationResource(): string
    {
        return 'attributes';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateAttributeData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateAttributeData::fromValidated($validated);
    }
}
