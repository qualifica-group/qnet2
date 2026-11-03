<?php

namespace App\Services\ChartOfAccounts;

use App\Models\RevenueType;

/**
 * Writes for the revenue sub-accounts (spec 0348). Always deletable in phase 1:
 * no document references them yet (D-10).
 */
class RevenueTypeService
{
    /**
     * @param  array<string, mixed>  $attributes  validated payload
     */
    public function create(array $attributes): RevenueType
    {
        return RevenueType::create($attributes)->fresh();
    }

    /**
     * @param  array<string, mixed>  $attributes  validated payload
     */
    public function update(RevenueType $revenueType, array $attributes): RevenueType
    {
        $revenueType->fill($attributes)->save();

        return $revenueType->fresh();
    }

    public function delete(RevenueType $revenueType): void
    {
        $revenueType->delete();
    }
}
