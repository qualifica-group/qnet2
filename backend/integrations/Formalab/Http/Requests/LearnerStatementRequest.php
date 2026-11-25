<?php

declare(strict_types=1);

namespace Integrations\Formalab\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Integrations\Formalab\Services\LearnerStatement\StatementCalculator;

/**
 * Reading or exporting a work order's "Prospetto contabile discenti"
 * (spec 0427). Authorization (`viewCosts`) is checked by the controller.
 */
class LearnerStatementRequest extends FormRequest
{
    public const string VARIANT_FULL = 'full';

    public const string VARIANT_PARTNERS = 'partners';

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
            'commission_scenario' => ['nullable', Rule::in([StatementCalculator::ECONOMIC, StatementCalculator::FINANCIAL])],
            'refresh' => ['sometimes', 'boolean'],
            'variant' => ['sometimes', Rule::in([self::VARIANT_FULL, self::VARIANT_PARTNERS])],
        ];
    }

    public function scenario(): ?string
    {
        $scenario = $this->validated('commission_scenario');

        return is_string($scenario) ? $scenario : null;
    }

    public function variant(): string
    {
        return (string) ($this->validated('variant') ?? self::VARIANT_FULL);
    }
}
