<?php

namespace App\Services\ChartOfAccounts;

use App\Models\ChartAccount;

/**
 * Writes for groups and accounts of the managerial chart (spec 0348). The tree
 * rules (D-9) are enforced by the FormRequests; this class only persists and
 * guards the restrictive delete (D-10).
 */
class ChartAccountService
{
    /**
     * @param  array<string, mixed>  $attributes  validated payload
     */
    public function create(array $attributes): ChartAccount
    {
        return $this->present(ChartAccount::create($attributes)->fresh());
    }

    /**
     * @param  array<string, mixed>  $attributes  validated payload
     */
    public function update(ChartAccount $chartAccount, array $attributes): ChartAccount
    {
        $chartAccount->fill($attributes)->save();

        return $this->present($chartAccount->fresh());
    }

    /**
     * A group with accounts, or an account with cost/revenue sub-accounts,
     * cannot be removed (it can be deactivated instead).
     */
    public function delete(ChartAccount $chartAccount): void
    {
        if ($chartAccount->hasChildren()) {
            abort(409, __('chart_accounts.in_use'));
        }

        $chartAccount->delete();
    }

    /**
     * Loads what ChartAccountResource needs: the parent (effective kind) and
     * the child counts.
     */
    public function present(ChartAccount $chartAccount): ChartAccount
    {
        return $chartAccount->loadMissing('parent')->loadCount(['children', 'costTypes', 'revenueTypes']);
    }
}
