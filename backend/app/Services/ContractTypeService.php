<?php

namespace App\Services;

use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\ContractType;
use Illuminate\Database\Eloquent\Builder;

/**
 * Business logic for the contract types catalogue (spec 0290 D-1).
 */
class ContractTypeService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ContractType
    {
        return ContractType::create($attributes)->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(ContractType $contractType, array $attributes): ContractType
    {
        $contractType->fill($attributes)->save();

        return $contractType;
    }

    /**
     * Restrictive delete (spec 0290 D-1): a type used by a contractual
     * condition is deactivated, never removed.
     */
    public function delete(ContractType $contractType): void
    {
        if ($contractType->employmentContracts()->exists()) {
            abort(409, __('contract_types.in_use'));
        }

        $contractType->delete();
    }

    /**
     * Active types in catalogue order (ADR 0011). `ids[]` hydrates the saved
     * value even when deactivated, without inflating the total.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = ContractType::query()->select(['id', 'name'])->where('is_active', true);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $result = $query->page($base, fn (Builder $ordered) => $this->ordered($ordered));

        if (! $query->hasIds()) {
            return $result;
        }

        $missingIds = array_values(array_diff($query->ids, $result->items->pluck('id')->all()));
        $hydrated = $this->ordered(ContractType::query()->select(['id', 'name'])->whereIn('id', $missingIds))->get();

        return $result->withItems($result->items->concat($hydrated));
    }

    /**
     * @param  Builder<ContractType>  $query
     * @return Builder<ContractType>
     */
    private function ordered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name')->orderBy('id');
    }
}
