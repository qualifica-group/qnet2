<?php

declare(strict_types=1);

namespace App\Http\Requests\DocumentBundles;

use App\DataObjects\DocumentBundles\CreateDocumentBundleData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/document-bundles (spec 0175, D-14).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('create', DocumentBundle::class)).
 * EnforcesFieldPermissions (spec 0004) additionally rejects any submitted
 * field the actor cannot edit (create-context, model = null). The bundle's
 * files are NOT part of this payload (uploaded separately via
 * /api/attachments).
 */
class StoreDocumentBundleRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:'.self::NAME_MAX, Rule::unique('document_bundles', 'name')],
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
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateDocumentBundleData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateDocumentBundleData::fromValidated($validated);
    }
}
