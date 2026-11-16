<?php

namespace App\Services\ChartOfAccounts;

use App\Models\RevenueType;
use App\Services\Deletion\InUseGuard;
use Illuminate\Support\Facades\DB;

/**
 * Writes for the revenue sub-accounts (spec 0348). Deletable while nothing uses them
 * (spec 0350, spec 0384).
 */
class RevenueTypeService
{
    public function __construct(private readonly InUseGuard $inUseGuard) {}

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
        return DB::transaction(function () use ($revenueType, $attributes): RevenueType {
            // Step 1: at most one institution-commission default (spec 0350, D-5)
            if (($attributes['is_institution_commission_default'] ?? false) === true) {
                RevenueType::query()->where('is_institution_commission_default', true)->whereKeyNot($revenueType->id)->get()
                    ->each(fn (RevenueType $other): bool => $other->update(['is_institution_commission_default' => false]));
            }

            // Step 2: persist
            $revenueType->fill($attributes)->save();

            return $revenueType->fresh();
        });
    }

    public function delete(RevenueType $revenueType): void
    {
        $this->inUseGuard->assertUnused($revenueType);

        $revenueType->delete();
    }
}
