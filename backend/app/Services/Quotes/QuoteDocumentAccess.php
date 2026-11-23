<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\Models\Contract;
use App\Models\Quote;
use App\Models\User;
use App\RequestManagement\RequestModule;
use App\Services\Contracts\ContractVisibilityScope;
use App\Services\RequestManagement\RequestManagementScope;

/**
 * Who may reach an offer's documents (spec 0415, D-2): anyone who can open at
 * least one screen that shows them, with that screen's own rule — the same
 * shape as OpportunityDocumentAccess.
 *
 *  1. the Offer detail: `view` through QuotePolicy (record scope);
 *  2. the work panel of Gestione Richieste / Gestione Iscritti: the module's
 *     `view` and the offer inside the actor's module perimeter. The operator
 *     roles hold no `quotes.view` and upload the documents from there;
 *  3. the Contract detail "Documenti offerta" tab: `contracts.view` and a
 *     visible contract on this offer.
 */
final class QuoteDocumentAccess
{
    public static function allows(User $user, Quote $quote): bool
    {
        return $user->can('view', $quote)
            || self::reachedThroughRequestManagement($user, $quote)
            || self::reachedThroughContract($user, $quote);
    }

    private static function reachedThroughRequestManagement(User $user, Quote $quote): bool
    {
        foreach (RequestModule::cases() as $module) {
            if (! $user->can($module->permission('view'))) {
                continue;
            }

            if (RequestManagementScope::scopeToActor(Quote::query()->whereKey($quote->id), $user, $module)->exists()) {
                return true;
            }
        }

        return false;
    }

    private static function reachedThroughContract(User $user, Quote $quote): bool
    {
        if (! $user->can('contracts.view')) {
            return false;
        }

        $contracts = Contract::query()->where('quote_id', $quote->id);

        return ContractVisibilityScope::scopeToActor($contracts, $user)->exists();
    }
}
