<?php

namespace App\Services\ChartOfAccounts;

use App\Models\RevenueType;
use Illuminate\Support\Facades\DB;

/**
 * Writes for the revenue sub-accounts (spec 0348). Deletable while no invoice line,
 * product or category references them (spec 0350).
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
        if ($this->isReferenced($revenueType)) {
            abort(409, __('chart_accounts.revenue_type_in_use'));
        }

        $revenueType->delete();
    }

    /**
     * Invoice lines, products and categories reference the sub-account (spec 0350).
     */
    private function isReferenced(RevenueType $revenueType): bool
    {
        return DB::table('invoice_lines')->where('revenue_type_id', $revenueType->id)->exists()
            || DB::table('products')->where('revenue_type_id', $revenueType->id)->exists()
            || DB::table('product_categories')->where('revenue_type_id', $revenueType->id)->exists();
    }
}
