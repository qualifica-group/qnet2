<?php

declare(strict_types=1);

namespace App\Services\PurchaseRequests;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * THE single implementation of the purchase request visibility rule (spec 0208,
 * D-16): an actor sees a request when they hold `purchase-requests.viewAll` (or
 * are super-admin through the Gate::before bypass) OR they are its requester,
 * its function manager or its author. The rule NARROWS, it never widens: the
 * `view` permission is still required.
 *
 * Query shape and record shape are the same predicate expressed twice, so the
 * grids and the Policy can never disagree. FAIL-CLOSED: a null actor is scoped
 * to a condition that cannot match any row. Static and stateless, like
 * WorkOrderVisibilityScope: TableDefinition::baseQuery() calls it inline and
 * the Policy must stay zero-argument constructible for `permissions:sync`.
 */
final class PurchaseRequestVisibilityScope
{
    public const string VIEW_ALL_PERMISSION = 'purchase-requests.viewAll';

    /**
     * @param  Builder<PurchaseRequest>  $query
     * @return Builder<PurchaseRequest>
     */
    public static function scopeToActor(Builder $query, ?User $user): Builder
    {
        if ($user?->can(self::VIEW_ALL_PERMISSION)) {
            return $query;
        }

        if ($user === null) {
            return $query->whereNull('purchase_requests.id');
        }

        return $query->where(static function (Builder $scoped) use ($user): void {
            $scoped->where('purchase_requests.requester_id', $user->id)
                ->orWhere('purchase_requests.function_manager_id', $user->id)
                ->orWhere('purchase_requests.created_by', $user->id);
        });
    }

    /**
     * The same rule over a query of lines (the "Gestione righe" grid).
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function scopeLinesToActor(Builder $query, ?User $user): Builder
    {
        if ($user?->can(self::VIEW_ALL_PERMISSION)) {
            return $query;
        }

        return $query->whereHas('purchaseRequest', static fn (Builder $request): Builder => self::scopeToActor($request, $user));
    }

    public static function isVisibleTo(User $user, PurchaseRequest $request): bool
    {
        return $user->can(self::VIEW_ALL_PERMISSION)
            || in_array($user->id, [$request->requester_id, $request->function_manager_id, $request->created_by], true);
    }
}
