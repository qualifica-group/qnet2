<?php

declare(strict_types=1);

namespace App\Services\Commissions\Settlements;

use App\Enums\CommissionAccrualType;
use App\Models\CommissionAccrual;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Selects the open movements a settlement can take (spec 0213, D-6): not
 * attached, not settled outside, owed to a referent, accrued up to the period
 * end. A role filter narrows the invoice-driven movements only: the manual
 * adjustments follow just the referent and the company.
 */
final class OpenMovementSelector
{
    /**
     * @param  array<int, string>|null  $roles
     * @return Builder<CommissionAccrual>
     */
    public function query(string $periodTo, ?array $roles = null): Builder
    {
        return CommissionAccrual::query()
            ->whereNull('commission_settlement_id')
            ->whereNull('settled_externally_on')
            ->where('recipient_type', 'referent')
            ->whereNotNull('company_id')
            ->whereDate('accrued_on', '<=', $periodTo)
            ->when($roles !== null && $roles !== [], fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner->where('type', CommissionAccrualType::Adjustment->value)->orWhereIn('recipient_role', $roles),
            ));
    }

    /**
     * The movements of one pair with a locking read, so two runs (or a run and
     * a recalculation) cannot attach the same movement twice.
     *
     * @param  array<int, string>|null  $roles
     * @return Builder<CommissionAccrual>
     */
    public function lockedQuery(int $referentId, int $companyId, string $periodTo, ?array $roles = null): Builder
    {
        return $this->query($periodTo, $roles)
            ->where('recipient_id', $referentId)
            ->where('company_id', $companyId)
            ->orderBy('id')
            ->lockForUpdate();
    }

    /**
     * @param  array<int, string>|null  $roles
     * @return Collection<int, CommissionAccrual>
     */
    public function lockedForPair(int $referentId, int $companyId, string $periodTo, ?array $roles = null): Collection
    {
        return $this->lockedQuery($referentId, $companyId, $periodTo, $roles)->get();
    }

    /**
     * The (referent, company) pairs holding open movements.
     *
     * @param  array<int, int>|null  $referentIds
     * @param  array<int, string>|null  $roles
     * @return Collection<int, CommissionAccrual> rows carrying only recipient_id and company_id
     */
    public function pairs(string $periodTo, ?int $companyId, ?array $referentIds, ?array $roles): Collection
    {
        return $this->query($periodTo, $roles)
            ->when($companyId !== null, fn (Builder $query) => $query->where('company_id', $companyId))
            ->when($referentIds !== null && $referentIds !== [], fn (Builder $query) => $query->whereIn('recipient_id', $referentIds))
            ->select('recipient_id', 'company_id')
            ->distinct()
            ->orderBy('recipient_id')
            ->orderBy('company_id')
            ->get();
    }
}
