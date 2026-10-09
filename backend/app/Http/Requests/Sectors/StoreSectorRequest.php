<?php

namespace App\Http\Requests\Sectors;

use App\DataObjects\Sectors\CreateSectorData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/sectors (spec 0018).
 *
 * A cycle is structurally impossible on create (the sector has no id yet),
 * so no cycle guard is needed here (unlike UpdateSectorRequest's
 * companion Service-level guard). Authorization is intentionally NOT
 * handled here (it stays in the controller via authorize('create',
 * Sector::class)). EnforcesFieldPermissions (spec 0004) additionally
 * rejects any submitted field the actor cannot edit (create context, model
 * = null).
 */
class StoreSectorRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        // Authorization handled in the controller via SectorPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Spec 0213: kept verbatim (01, 07a, NA), unique when present.
            'code' => ['nullable', 'string', 'max:20', Rule::unique('sectors', 'code')],
            'name' => ['required', 'string', 'max:191'],
            'parent_id' => ['nullable', 'integer', 'exists:sectors,id'],
            // Spec 0212: omitted = true (active).
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
        return 'sectors';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateSectorData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateSectorData::fromValidated($validated);
    }
}
