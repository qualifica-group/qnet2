<?php

declare(strict_types=1);

namespace App\Http\Requests\DocumentLayouts;

use App\DataObjects\DocumentLayouts\UpdateDocumentLayoutData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\DocumentLayout;
use App\Services\DocumentLayouts\DocumentLayoutConfigValidator;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/document-layouts/{documentLayout}
 * (spec 0069). Every field is `sometimes` to support partial PATCH updates.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('update', $documentLayout)). EnforcesFieldPermissions (spec
 * 0004) additionally rejects any submitted field the actor cannot edit on
 * this specific model. `name` is unique WITHIN the model's CURRENT `module`
 * (immutable, see below), ignoring self.
 *
 * `code`/`module` (D-2): BOTH `prohibited`, UNCONDITIONALLY — the keys must
 * not even be present in the payload, regardless of their value (identical or
 * different from the persisted one) and regardless of the actor's role. Field
 * permissions alone cannot express this: `AbstractResourceAuthorization::
 * fieldPermissions()` bypasses every ceiling for the privileged role, so the
 * immutability guard lives here instead, ahead of and independent from that
 * mechanism (mirrors UpdatePaymentMethodRequest's `code`).
 *
 * `is_default`'s D-7 transition rules (D-7c/d/e) are NOT enforced here: they
 * need the model's CURRENT `is_default`/`is_active` inside the same
 * transaction as the write, so they live in
 * App\Services\DocumentLayouts\DocumentLayoutDefaultManager, called by
 * DocumentLayoutService::update() — running only AFTER the controller's own
 * authorize('update', ...) has already passed, which keeps the same 403-
 * before-422 precedence as everything else here without duplicating an
 * ability check for it.
 *
 * `config`'s deep structural shape is validated in withValidator() the same
 * way as StoreDocumentLayoutRequest — see that class' docblock for the
 * AC-053 precedence reasoning.
 */
class UpdateDocumentLayoutRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    private const int NAME_MAX = 191;

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
        /** @var DocumentLayout $documentLayout */
        $documentLayout = $this->route('documentLayout');

        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:'.self::NAME_MAX,
                Rule::unique('document_layouts', 'name')
                    ->where(fn ($query) => $query->where('module', $documentLayout->module->value))
                    ->ignore($documentLayout->id),
            ],
            'code' => ['prohibited'],
            'module' => ['prohibited'],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.self::DESCRIPTION_MAX],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'config' => ['sometimes', 'required', 'array'],
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
     * Delegates the deep shape of a SUBMITTED `config` (absent on a partial
     * PATCH that does not touch it) to DocumentLayoutConfigValidator, only
     * once the actor has the base `update` ability — see class docblock.
     */
    private function validateConfigStructure(Validator $validator): void
    {
        if (! $this->user()->can('document-layouts.update') || ! $this->has('config')) {
            return;
        }

        $config = $this->input('config');

        if (! is_array($config)) {
            return;
        }

        /** @var DocumentLayout $documentLayout */
        $documentLayout = $this->route('documentLayout');

        $errors = app(DocumentLayoutConfigValidator::class)->validate($config, $documentLayout, $documentLayout->module, $this->user());

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
        /** @var DocumentLayout $documentLayout */
        $documentLayout = $this->route('documentLayout');

        return $documentLayout;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateDocumentLayoutData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateDocumentLayoutData::fromValidated($validated);
    }
}
