<?php

namespace App\Http\Requests\OperationalSites;

use App\DataObjects\OperationalSites\CreateOperationalSiteData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/operational-sites (spec 0011).
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('create', OperationalSite::class)). `city_id`/`line1` are the
 * only required fields (spec 0008 mandatory); the geo FK cascade is
 * otherwise nullable. EnforcesFieldPermissions (spec 0004) additionally
 * rejects any submitted field the actor cannot edit (create-context, model =
 * null).
 */
class StoreOperationalSiteRequest extends FormRequest
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
            'alias' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'line1' => ['required', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],
            'state_id' => ['nullable', 'integer', Rule::exists('states', 'id')],
            'province_id' => ['nullable', 'integer', Rule::exists('provinces', 'id')],
            'city_id' => ['required', 'integer', Rule::exists('cities', 'id')],
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
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateOperationalSiteData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateOperationalSiteData::fromValidated($validated);
    }
}
