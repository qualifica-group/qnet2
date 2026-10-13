<?php

declare(strict_types=1);

namespace App\Services\Commissions\Settlements;

use App\Models\CommissionSettlement;
use App\Models\CommissionSettlementRun;
use Illuminate\Support\Collection;

/**
 * A run with what its response needs (spec 0213): the settlements it created
 * and the names of the referents and companies it mentions, loaded in a fixed
 * number of queries.
 */
final readonly class SettlementRunView
{
    /**
     * @param  Collection<int, CommissionSettlement>  $settlements
     * @param  array<int, string>  $referentNames  by id
     * @param  array<int, string>  $companyNames  by id
     */
    public function __construct(
        public CommissionSettlementRun $run,
        public Collection $settlements,
        public array $referentNames,
        public array $companyNames,
    ) {}
}
