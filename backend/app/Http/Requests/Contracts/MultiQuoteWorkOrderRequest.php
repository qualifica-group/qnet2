<?php

declare(strict_types=1);

namespace App\Http\Requests\Contracts;

/**
 * Body of POST /api/contracts/multi-quote-work-orders (spec 0267, D-3): the
 * common "Programma" fields of GenerateContractWorkOrderRequest plus the
 * selected contracts, with at least two distinct lines. Membership, branch and
 * "already programmed" rules need the real rows, so they stay in
 * WorkOrderLineWriter / MultiQuoteLineRules.
 */
class MultiQuoteWorkOrderRequest extends GenerateContractWorkOrderRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            ...MultiProgrammableLinesRequest::contractIdsRules(),
            'quote_line_ids' => ['required', 'array', 'min:2'],
            'quote_line_ids.*' => ['integer', 'distinct'],
        ];
    }
}
