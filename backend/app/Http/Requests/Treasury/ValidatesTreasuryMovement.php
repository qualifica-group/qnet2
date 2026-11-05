<?php

namespace App\Http\Requests\Treasury;

use App\Enums\TreasuryMovementDirection;
use App\Enums\TreasuryMovementType;
use App\Models\TreasuryMovement;
use App\Services\Treasury\TreasuryMovementRuleChecker;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * Rules shared by the store and update requests of a treasury movement (spec 0355):
 * field-level rules here, cross-field rules in TreasuryMovementRuleChecker.
 */
trait ValidatesTreasuryMovement
{
    private const int DESCRIPTION_MAX = 255;

    private const string AMOUNT_MAX = '999999999999.99';

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function movementRules(bool $partial): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];

        return [
            'type' => [...$required, Rule::enum(TreasuryMovementType::class)],
            'direction' => ['sometimes', 'nullable', Rule::enum(TreasuryMovementDirection::class)],
            'movement_date' => [...$required, 'date_format:Y-m-d'],
            'financial_account_id' => [...$required, 'integer', 'exists:financial_accounts,id'],
            'counterpart_account_id' => ['sometimes', 'nullable', 'integer', 'exists:financial_accounts,id'],
            'amount' => [...$required, 'numeric', 'gt:0', 'max:'.self::AMOUNT_MAX, 'decimal:0,2'],
            'description' => [...$required, 'string', 'min:1', 'max:'.self::DESCRIPTION_MAX],
            'cost_type_id' => ['sometimes', 'nullable', 'integer', 'exists:cost_types,id'],
            'revenue_type_id' => ['sometimes', 'nullable', 'integer', 'exists:revenue_types,id'],
            'treasury_type_id' => ['sometimes', 'nullable', 'integer', 'exists:treasury_types,id'],
            'registry_id' => ['sometimes', 'nullable', 'integer', 'exists:registries,id'],
            'work_order_id' => ['sometimes', 'nullable', 'integer', 'exists:work_orders,id'],
        ];
    }

    /**
     * Cross-field checks, run only once every field-level rule passed.
     */
    protected function validateMovement(Validator $validator, ?TreasuryMovement $existing): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $checker = app(TreasuryMovementRuleChecker::class);
        $errors = $checker->check($checker->effective($validator->validated(), $existing), $existing);

        foreach ($errors as $field => $message) {
            $validator->errors()->add($field, $message);
        }
    }
}
