<?php

declare(strict_types=1);

namespace Integrations\Formalab\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Integrations\Formalab\Services\LearnerStatement\StatementCalculator;

/**
 * The supplier registry a commercial partner is paid through (spec 0427);
 * null clears it. Authorization (`manageCosts`) is checked by the controller.
 */
class UpdatePartnerSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'partner' => ['required', 'string', 'max:255'],
            'supplier_id' => ['nullable', 'integer', Rule::exists('registries', 'id')],
            'commission_scenario' => ['nullable', Rule::in([StatementCalculator::ECONOMIC, StatementCalculator::FINANCIAL])],
        ];
    }

    public function scenario(): ?string
    {
        $scenario = $this->validated('commission_scenario');

        return is_string($scenario) ? $scenario : null;
    }

    public function supplierId(): ?int
    {
        $id = $this->validated('supplier_id');

        return $id === null ? null : (int) $id;
    }
}
