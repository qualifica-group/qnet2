<?php

namespace App\Services\ChartOfAccounts;

use App\Models\TreasuryType;
use Illuminate\Support\Facades\DB;

/**
 * Writes for the financial sub-accounts (spec 0360, D-3). Deletable while no
 * treasury movement references them.
 */
class TreasuryTypeService
{
    /**
     * @param  array<string, mixed>  $attributes  validated payload
     */
    public function create(array $attributes): TreasuryType
    {
        return TreasuryType::create($attributes)->fresh();
    }

    /**
     * @param  array<string, mixed>  $attributes  validated payload
     */
    public function update(TreasuryType $treasuryType, array $attributes): TreasuryType
    {
        $treasuryType->fill($attributes)->save();

        return $treasuryType->fresh();
    }

    public function delete(TreasuryType $treasuryType): void
    {
        if (DB::table('treasury_movements')->where('treasury_type_id', $treasuryType->id)->exists()) {
            abort(409, __('chart_accounts.treasury_type_in_use'));
        }

        $treasuryType->delete();
    }
}
