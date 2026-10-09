<?php

namespace App\Http\Requests\Sectors;

use App\DataObjects\Sectors\UpdateSectorData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\Sector;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for PUT/PATCH /api/sectors/{sector}
 * (spec 0018). Every field is `sometimes` to support partial PATCH
 * updates. The anti-cycle guard (parent_id cannot be the sector itself or
 * one of its own descendants) is enforced by SectorService, not here (it
 * needs to walk the tree). Authorization is intentionally NOT handled here
 * (it stays in the controller via authorize('update', $sector)).
 * EnforcesFieldPermissions (spec 0004) additionally rejects any submitted
 * field the actor cannot edit on this specific model.
 */
class UpdateSectorRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:sectors,id'],
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
        /** @var Sector $sector */
        $sector = $this->route('sector');

        return $sector;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateSectorData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateSectorData::fromValidated($validated);
    }
}
