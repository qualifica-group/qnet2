<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\User;
use App\Services\Quotes\QuoteVisibilityScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Record visibility of Contratti (spec 0316, D-3): a contract follows its
 * quote, judged with the contracts' OWN `viewSite`/`viewAll` permissions —
 * independent from the quotes' ones. The predicate lives once, in
 * QuoteVisibilityScope; this class only routes a contract through its quote.
 * FAIL-CLOSED: a null actor matches no row.
 */
final class ContractVisibilityScope
{
    public const RESOURCE = 'contracts';

    /**
     * @param  Builder<Contract>  $query
     * @return Builder<Contract>
     */
    public static function scopeToActor(Builder $query, ?User $user): Builder
    {
        if ($user === null) {
            return $query->whereNull('contracts.id');
        }

        if ($user->can(self::RESOURCE.'.'.QuoteVisibilityScope::VIEW_ALL_ABILITY)) {
            return $query;
        }

        return $query->whereHas('quote', fn (Builder $quote) => QuoteVisibilityScope::scopeToActor($quote, $user, self::RESOURCE));
    }

    public static function isVisibleTo(User $user, Contract $contract): bool
    {
        if ($user->can(self::RESOURCE.'.'.QuoteVisibilityScope::VIEW_ALL_ABILITY)) {
            return true;
        }

        $quote = $contract->loadMissing('quote')->quote;

        return $quote !== null && QuoteVisibilityScope::isVisibleTo($user, $quote, self::RESOURCE);
    }
}
