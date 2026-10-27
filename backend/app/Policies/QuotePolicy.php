<?php

namespace App\Policies;

use App\Models\Quote;
use App\Models\User;
use App\Policies\Abstracts\BasePolicy;
use App\Services\Quotes\QuoteVisibilityScope;
use Illuminate\Database\Eloquent\Model;

/**
 * CRUD policy for the `quotes` resource (spec 0065, MT-05), plus the record
 * visibility of spec 0316: view/update/delete need the permission AND the
 * quote to be in the actor's scope (own, own site, or `quotes.viewAll`).
 * `viewSite`/`viewAll` only narrow; they never grant `view`.
 */
class QuotePolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'quotes';
    }

    public function view(User $user, Model $model): bool
    {
        return parent::view($user, $model) && $this->isInScope($user, $model);
    }

    public function update(User $user, Model $model): bool
    {
        return parent::update($user, $model) && $this->isInScope($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return parent::delete($user, $model) && $this->isInScope($user, $model);
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

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return [...parent::abilities(), QuoteVisibilityScope::VIEW_SITE_ABILITY, QuoteVisibilityScope::VIEW_ALL_ABILITY];
    }

    private function isInScope(User $user, Model $model): bool
    {
        return ! $model instanceof Quote || QuoteVisibilityScope::isVisibleTo($user, $model);
    }
}
