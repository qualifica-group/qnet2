<?php

declare(strict_types=1);

namespace App\Services\Opportunities;

use App\Models\Contract;
use App\Models\Opportunity;
use App\Models\User;
use App\RequestManagement\RequestModule;
use App\Services\Contracts\ContractVisibilityScope;
use App\Services\RequestManagement\RequestManagementScope;

/**
 * Who may reach an opportunity's documents (spec 0316, D-9): anyone who can
 * open at least one screen that shows them, with that screen's own rule.
 *
 *  1. the Opportunity detail: `view` through OpportunityPolicy (record scope);
 *  2. the work panel of Gestione Richieste / Gestione Iscritti: the notes rule
 *     (RequestManagementScope::reachesOpportunity). The operator roles hold
 *     no `opportunities.view` and work the documents from there;
 *  3. the Contract detail "Documenti opportunita'" tab: `contracts.view` and a
 *     visible contract whose offer belongs to the opportunity.
 */
final class OpportunityDocumentAccess
{
    public static function allows(User $user, Opportunity $opportunity): bool
    {
        return $user->can('view', $opportunity)
            || self::reachedThroughRequestManagement($user, $opportunity)
            || self::reachedThroughContract($user, $opportunity);
    }

    private static function reachedThroughRequestManagement(User $user, Opportunity $opportunity): bool
    {
        foreach (RequestModule::cases() as $module) {
            if (RequestManagementScope::reachesOpportunity($user, $opportunity->id, $module)) {
                return true;
            }
        }

        return false;
    }

    private static function reachedThroughContract(User $user, Opportunity $opportunity): bool
    {
        if (! $user->can('contracts.view')) {
            return false;
        }

        $contracts = Contract::query()
            ->whereHas('quote', fn ($quote) => $quote->where('opportunity_id', $opportunity->id));

        return ContractVisibilityScope::scopeToActor($contracts, $user)->exists();
    }
}
