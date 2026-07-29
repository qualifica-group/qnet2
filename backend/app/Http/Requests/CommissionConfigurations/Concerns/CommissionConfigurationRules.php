<?php

declare(strict_types=1);

namespace App\Http\Requests\CommissionConfigurations\Concerns;

use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionConfigurationStatus;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;
use Illuminate\Validation\Rule;

trait CommissionConfigurationRules
{
    /** @return array<string, array<int, mixed>> */
    protected function configurationRules(bool $partial): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];

        return [
            'name' => [...$required, 'string', 'max:191'],
            'recipient_role' => [...$required, Rule::enum(CommissionRecipientRole::class)],
            'application_scope' => [...$required, Rule::enum(CommissionApplicationScope::class)],
            'product_category_id' => [
                'nullable', 'integer', Rule::exists('product_categories', 'id'),
                Rule::requiredIf(fn () => $this->input('application_scope') === CommissionApplicationScope::ProductCategory->value),
                Rule::prohibitedIf(fn () => $this->input('application_scope') === CommissionApplicationScope::Product->value),
            ],
            'product_id' => [
                'nullable', 'integer', Rule::exists('products', 'id'),
                Rule::requiredIf(fn () => $this->input('application_scope') === CommissionApplicationScope::Product->value),
                Rule::prohibitedIf(fn () => $this->input('application_scope') === CommissionApplicationScope::ProductCategory->value),
            ],
            'commission_type' => [...$required, Rule::enum(CommissionType::class)],
            'value' => [...$required, 'numeric', 'min:0', 'decimal:0,4', 'max:99999999999.9999'],
            'priority' => [...$required, 'integer', 'min:0'],
            'valid_from' => [...$required, 'date_format:Y-m-d'],
            'valid_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
            'status' => [...$required, Rule::enum(CommissionConfigurationStatus::class)],
            'internal_note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
