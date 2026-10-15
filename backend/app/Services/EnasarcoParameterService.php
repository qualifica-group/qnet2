<?php

namespace App\Services;

use App\DataObjects\EnasarcoParameters\EnasarcoParameterData;
use App\Enums\CommissionSettlementStatus;
use App\Enums\HttpStatusEnum;
use App\Models\CommissionSettlement;
use App\Models\EnasarcoParameter;

/**
 * Business logic for the `enasarco-parameters` resource (spec 0213, D-3).
 * A year already consumed by an issued settlement cannot be deleted: the
 * settlement keeps its snapshot, but the parameters stay as the audit source.
 */
class EnasarcoParameterService
{
    public function create(EnasarcoParameterData $data): EnasarcoParameter
    {
        return EnasarcoParameter::create($data->toAttributes())->fresh();
    }

    public function update(EnasarcoParameter $parameter, EnasarcoParameterData $data): EnasarcoParameter
    {
        $parameter->fill($data->toAttributes())->save();

        return $parameter->fresh();
    }

    public function delete(EnasarcoParameter $parameter): void
    {
        if ($this->isUsedByIssuedSettlement($parameter)) {
            abort(HttpStatusEnum::CONFLICT->value, __('This ENASARCO year is used by an issued settlement.'));
        }

        $parameter->delete();
    }

    /**
     * Half-open range on period_to (not YEAR(), not BETWEEN): portable across
     * SQLite and MySQL, safe if the column carries a time part, and index-friendly.
     */
    private function isUsedByIssuedSettlement(EnasarcoParameter $parameter): bool
    {
        return CommissionSettlement::query()
            ->whereIn('status', array_column(CommissionSettlementStatus::validStatuses(), 'value'))
            ->where('enasarco_enabled', true)
            ->where('period_to', '>=', "{$parameter->year}-01-01")
            ->where('period_to', '<', ($parameter->year + 1).'-01-01')
            ->exists();
    }
}
