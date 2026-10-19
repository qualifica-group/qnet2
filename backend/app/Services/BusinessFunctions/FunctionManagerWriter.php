<?php

namespace App\Services\BusinessFunctions;

use App\Models\BusinessFunction;
use App\Models\User;

/**
 * Writes the functions a person manages from the user side (spec 0276 D-5):
 * `business_functions.manager_id` stays the single source of truth, this only
 * sets it from the other end. Each function is updated through its model so
 * the function's own activity log records the change of manager.
 *
 * The caller (EmploymentWriter, inside UserService's transaction) decides
 * whether to call at all: absent/null in the request means "unchanged".
 */
class FunctionManagerWriter
{
    /**
     * Make $user the manager of exactly $functionIds: listed functions take
     * the user (whoever managed them before loses them), functions managed
     * before and no longer listed are left without a manager.
     *
     * @param  array<int, int>  $functionIds
     */
    public function sync(User $user, array $functionIds): void
    {
        BusinessFunction::query()
            ->where('manager_id', $user->id)
            ->whereNotIn('id', $functionIds)
            ->get()
            ->each(fn (BusinessFunction $function): bool => $function->update(['manager_id' => null]));

        BusinessFunction::query()
            ->whereIn('id', $functionIds)
            ->where(fn ($query) => $query->whereNull('manager_id')->orWhere('manager_id', '!=', $user->id))
            ->get()
            ->each(fn (BusinessFunction $function): bool => $function->update(['manager_id' => $user->id]));

        $user->unsetRelation('managedBusinessFunctions');
    }
}
