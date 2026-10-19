<?php

namespace App\Services\BusinessFunctions;

use App\Models\BusinessFunction;
use Illuminate\Validation\ValidationException;

/**
 * Keeps the mandatory membership rule (spec 0275 D-4/D-5) true from the
 * business-function side: an active, non-service-account person must always
 * belong to at least one function, so a write that would strand one is refused.
 * Called by BusinessFunctionService inside its transaction, before any write.
 */
class BusinessFunctionMembershipGuard
{
    private const string NAMES_SEPARATOR = ', ';

    /**
     * A `users` replace on $function must not strand a removed member.
     *
     * @param  array<int, int>  $keptUserIds  the full member set being written
     *
     * @throws ValidationException
     */
    public function assertCanReplaceMembers(BusinessFunction $function, array $keptUserIds): void
    {
        $names = $this->strandedNames($function, $keptUserIds);

        if ($names !== []) {
            throw ValidationException::withMessages([
                'users' => __('business_functions.members_would_be_unassigned', ['names' => implode(self::NAMES_SEPARATOR, $names)]),
            ]);
        }
    }

    /**
     * Deleting $function must not strand a member (409, like the other delete constraints).
     */
    public function assertCanDelete(BusinessFunction $function): void
    {
        $names = $this->strandedNames($function, []);

        if ($names !== []) {
            abort(409, __('business_functions.delete_members_would_be_unassigned', ['names' => implode(self::NAMES_SEPARATOR, $names)]));
        }
    }

    /**
     * Names of the active, non-service members of $function that are not in
     * $keptUserIds and belong to no other function.
     *
     * @param  array<int, int>  $keptUserIds
     * @return list<string>
     */
    private function strandedNames(BusinessFunction $function, array $keptUserIds): array
    {
        return $function->users()
            ->where('users.is_active', true)
            ->excludingServiceAccounts()
            ->whereNotIn('users.id', $keptUserIds)
            ->whereDoesntHave('businessFunctions', static fn ($query) => $query->where('business_functions.id', '!=', $function->id))
            ->orderBy('users.name')
            ->pluck('users.name')
            ->all();
    }
}
