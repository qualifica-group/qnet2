<?php

declare(strict_types=1);

namespace Integrations\Formalab\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Integrations\Formalab\Services\LearnerStatement\StatementCalculator;

/**
 * A learner's commission in the prospetto (spec 0427 D-3): a percentage of the
 * profit per learner or a fixed amount. Authorization (`manageCosts`) is
 * checked by the controller.
 */
class UpdateLearnerCommissionRequest extends FormRequest
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
            'percentage' => ['nullable', 'required_without:amount', 'numeric'],
            'amount' => ['nullable', 'required_without:percentage', 'numeric'],
            'commission_scenario' => ['nullable', Rule::in([StatementCalculator::ECONOMIC, StatementCalculator::FINANCIAL])],
        ];
    }

    public function scenario(): ?string
    {
        $scenario = $this->validated('commission_scenario');

        return is_string($scenario) ? $scenario : null;
    }
}
