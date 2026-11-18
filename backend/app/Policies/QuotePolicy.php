<?php

namespace App\Policies;

use App\Enums\TeamActivityRecordEnum;
use App\Models\Quote;
use App\Models\User;
use App\Policies\Abstracts\BasePolicy;
use App\Services\Quotes\ContractedQuoteGuard;
use App\Services\Quotes\QuoteLineWorkOrderLock;
use App\Services\Quotes\QuoteRegistryTransferService;
use App\Services\Quotes\QuoteVisibilityScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * CRUD policy for the `quotes` resource (spec 0065, MT-05), plus the record
 * visibility of spec 0316: view/update/delete need the permission AND the
 * quote to be in the actor's scope (own, own site, or `quotes.viewAll`).
 * `viewSite`/`viewAll` only narrow; they never grant `view`.
 */
class QuotePolicy extends BasePolicy
{
    /** Spec 0412, D-1: costs and margins of the offer. */
    public const string VIEW_COSTS_ABILITY = 'viewCosts';

    /** The permission behind VIEW_COSTS_ABILITY, for the checks with no record (grid, statistics, documents). */
    public const string VIEW_COSTS_PERMISSION = 'quotes.'.self::VIEW_COSTS_ABILITY;

    protected function resource(): string
    {
        return 'quotes';
    }

    public function view(User $user, Model $model): bool|Response
    {
        return $this->withinPerimeter($user->can($this->permission('view')), fn (): bool => $this->isInScope($user, $model), 'quote_out_of_scope');
    }

    public function update(User $user, Model $model): bool|Response
    {
        return $this->withinPerimeter($user->can($this->permission('update')), fn (): bool => $this->isInScope($user, $model), 'quote_out_of_scope');
    }

    public function delete(User $user, Model $model): bool|Response
    {
        return $this->withinPerimeter($user->can($this->permission('delete')), fn (): bool => $this->isInScope($user, $model), 'quote_out_of_scope');
    }

    /** Resource-level: lifts the record scoping to every quote. */
    public function viewAll(User $user): bool
    {
        return $user->can($this->permission(QuoteVisibilityScope::VIEW_ALL_ABILITY));
    }

    /** Resource-level: adds the quotes of the actor's operational sites. */
    public function viewSite(User $user): bool
    {
        return $user->can($this->permission(QuoteVisibilityScope::VIEW_SITE_ABILITY));
    }

    /** Resource-level (spec 0340): adds the quotes whose revenue categories fall in the actor's competence. */
    public function viewCompetence(User $user): bool
    {
        return $user->can($this->permission(QuoteVisibilityScope::VIEW_COMPETENCE_ABILITY));
    }

    /**
     * Spec 0412, D-1: cost lines, cost and margin of the offer; permission
     * plus the same record scope as view, like WorkOrderPolicy::viewCosts.
     */
    public function viewCosts(User $user, Quote $quote): Response
    {
        return $this->withinPerimeter($user->can(self::VIEW_COSTS_PERMISSION), fn (): bool => $this->isInScope($user, $quote), 'quote_out_of_scope');
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return [...parent::abilities(), QuoteVisibilityScope::VIEW_SITE_ABILITY, QuoteVisibilityScope::VIEW_ALL_ABILITY, QuoteVisibilityScope::VIEW_COMPETENCE_ABILITY, QuoteLineWorkOrderLock::OVERRIDE_ABILITY, QuoteRegistryTransferService::ABILITY, ContractedQuoteGuard::ABILITY, TeamActivityRecordEnum::ABILITY, self::VIEW_COSTS_ABILITY];
    }

    private function isInScope(User $user, Model $model): bool
    {
        return ! $model instanceof Quote || QuoteVisibilityScope::isVisibleTo($user, $model);
    }
}
