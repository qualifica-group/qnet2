<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\QuoteLine;
use Illuminate\Http\Request;

/**
 * One row of GET /api/contracts/multi-programmable-lines (spec 0267, D-3): the
 * single-contract projection plus the contract it came from, its branch root
 * and whether it may join a multi-offer commessa.
 *
 * @mixin QuoteLine
 */
class MultiProgrammableQuoteLineResource extends ProgrammableQuoteLineResource
{
    /**
     * @param  array{id: int, name: string}|null  $branchRoot
     */
    public function __construct(
        QuoteLine $resource,
        private readonly int $contractId,
        private readonly ?array $branchRoot,
        private readonly bool $multiQuoteEligible,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'contract_id' => $this->contractId,
            'quote_id' => $this->quote_id,
            'branch_root' => $this->branchRoot,
            'multi_quote_eligible' => $this->multiQuoteEligible,
        ];
    }
}
