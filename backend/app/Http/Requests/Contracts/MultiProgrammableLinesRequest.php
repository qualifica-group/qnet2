<?php

declare(strict_types=1);

namespace App\Http\Requests\Contracts;

use App\Services\Contracts\MultiQuoteWorkOrderProgrammer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query of GET /api/contracts/multi-programmable-lines (spec 0267, D-3). The
 * per-contract gate (`program` + ClosedWon) is NOT here: it is applied by
 * MultiQuoteContractGate in the controller.
 */
class MultiProgrammableLinesRequest extends FormRequest
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
        return self::contractIdsRules();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function contractIdsRules(): array
    {
        return [
            'contract_ids' => ['required', 'array', 'min:2', 'max:'.MultiQuoteWorkOrderProgrammer::MAX_CONTRACTS],
            'contract_ids.*' => ['integer', 'distinct', Rule::exists('contracts', 'id')],
        ];
    }
}
