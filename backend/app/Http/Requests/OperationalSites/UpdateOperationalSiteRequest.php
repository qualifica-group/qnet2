<?php

namespace App\Http\Requests\OperationalSites;

use App\DataObjects\OperationalSites\UpdateOperationalSiteData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\OperationalSite;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/operational-sites/{operationalSite}
 * (spec 0011). Every field is `sometimes` to support TRUE partial PATCH
 * updates (AC-011): a submitted field rewrites the site's primary address'
 * matching column; an unsubmitted field leaves it untouched (the Service
 * merges onto the current address — see OperationalSiteService).
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('update', $operationalSite)). EnforcesFieldPermissions (spec
 * 0004) additionally rejects any submitted field the actor cannot edit on this
 * specific model.
 */
class UpdateOperationalSiteRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        // Authorization handled in the controller via OperationalSitePolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'alias' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'line1' => ['sometimes', 'required', 'string', 'max:255'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'country_id' => ['sometimes', 'nullable', 'integer', Rule::exists('countries', 'id')],
            'state_id' => ['sometimes', 'nullable', 'integer', Rule::exists('states', 'id')],
            'province_id' => ['sometimes', 'nullable', 'integer', Rule::exists('provinces', 'id')],
            'city_id' => ['sometimes', 'required', 'integer', Rule::exists('cities', 'id')],
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
        return 'operational-sites';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var OperationalSite $operationalSite */
        $operationalSite = $this->route('operationalSite');

        return $operationalSite;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateOperationalSiteData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateOperationalSiteData::fromValidated($validated);
    }
}
