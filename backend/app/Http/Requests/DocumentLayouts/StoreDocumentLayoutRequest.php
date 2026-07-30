<?php

declare(strict_types=1);

namespace App\Http\Requests\DocumentLayouts;

use App\DataObjects\DocumentLayouts\CreateDocumentLayoutData;
use App\Enums\DocumentLayoutModule;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Services\DocumentLayouts\DocumentLayoutConfigValidator;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/document-layouts (spec 0069).
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('create', DocumentLayout::class)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * (create-context, model = null). `name` is unique WITHIN `module`; `code` is
 * unique GLOBALLY and the ONLY place it is ever writable (D-2): permanently
 * immutable once persisted, enforced by UpdateDocumentLayoutRequest's
 * `prohibited` rule (same for `module`).
 *
 * `config`'s deep structural shape (block types, enums, dimensional limits —
 * data_contract's `config_schema`) is validated in withValidator() by
 * DocumentLayoutConfigValidator, gated behind the actor's base `create`
 * ability so a 403 (no create permission) always precedes a config-shape 422
 * (AC-053) — the same precedence EnforcesFieldPermissions itself already
 * gives its own field-level checks.
 */
class StoreDocumentLayoutRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    private const int NAME_MAX = 191;

    private const int CODE_MAX = 64;

    private const int DESCRIPTION_MAX = 500;

    public function authorize(): bool
    {
        // Authorization handled in the controller via DocumentLayoutPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:'.self::NAME_MAX,
                Rule::unique('document_layouts', 'name')->where(fn ($query) => $query->where('module', $this->input('module'))),
            ],
            'code' => ['required', 'string', 'max:'.self::CODE_MAX, 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('document_layouts', 'code')],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.self::DESCRIPTION_MAX],
            'module' => ['required', Rule::enum(DocumentLayoutModule::class)],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'config' => ['required', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
            $this->validateConfigStructure($validator);
        });
    }

    /**
     * Delegates the deep shape of `config` to DocumentLayoutConfigValidator
     * (data_contract's `config_schema`), only once the actor has the base
     * write ability and `module` itself resolved to a known enum case —
     * validating against an unknown module would be meaningless.
     */
    private function validateConfigStructure(Validator $validator): void
    {
        if (! $this->user()->can('document-layouts.create')) {
            return;
        }

        $module = DocumentLayoutModule::tryFrom((string) $this->input('module'));
        $config = $this->input('config');

        if ($module === null || ! is_array($config)) {
            return;
        }

        $errors = app(DocumentLayoutConfigValidator::class)->validate($config, null, $module, $this->user());

        foreach ($errors as $path => $message) {
            $validator->errors()->add($path, $message);
        }
    }

    protected function authorizationResource(): string
    {
        return 'document-layouts';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateDocumentLayoutData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateDocumentLayoutData::fromValidated($validated);
    }
}
