<?php

declare(strict_types=1);

namespace App\Http\Requests\DocumentBundles;

use App\DataObjects\DocumentBundles\UpdateDocumentBundleData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\DocumentBundle;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/document-bundles/{documentBundle}
 * (spec 0175, D-14). Every field is `sometimes` to support partial PATCH
 * updates.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('update', $documentBundle)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted key the actor cannot edit
 * on this specific model.
 */
class UpdateDocumentBundleRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    private const int NAME_MAX = 191;

    public function authorize(): bool
    {
        // Authorization handled in the controller via DocumentBundlePolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $documentBundle = $this->route('documentBundle');
        $ignoreId = $documentBundle instanceof DocumentBundle ? $documentBundle->id : null;

        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:'.self::NAME_MAX,
                Rule::unique('document_bundles', 'name')->ignore($ignoreId),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'document-bundles';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var DocumentBundle $documentBundle */
        $documentBundle = $this->route('documentBundle');

        return $documentBundle;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateDocumentBundleData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateDocumentBundleData::fromValidated($validated);
    }
}
