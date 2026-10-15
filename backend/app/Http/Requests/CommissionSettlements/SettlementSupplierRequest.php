<?php

namespace App\Http\Requests\CommissionSettlements;

use App\Models\Registry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates POST /api/commission-settlements/{commissionSettlement}/supplier
 * (spec 0267, D-7): an existing registry to link, or null to create one from
 * the agent card (which also needs `registries.create`). Authorization runs
 * before validation, so a missing permission is a 403 whatever the body.
 */
class SettlementSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user->can('approve', $this->route('commissionSettlement'))
            && ($this->input('registry_id') !== null || $user->can('create', Registry::class));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'registry_id' => ['present', 'nullable', 'integer', Rule::exists('registries', 'id')],
        ];
    }

    public function registryId(): ?int
    {
        $id = $this->safe()->input('registry_id');

        return $id === null ? null : (int) $id;
    }
}
