<?php

namespace App\Services\Users;

use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * The mandatory business function rule (spec 0275 D-4, moved from the
 * competences of spec 0271 D-6): an active, non-service-account person must
 * belong to at least one business function, i.e. own at least one
 * `business_function_user` row. Competences are optional.
 *
 * Checked by UserService inside the write transaction, only when the request
 * touched `employment` or turned the user active (or on create), so an update
 * that touches neither never blocks existing legacy data. The exception rolls
 * the whole transaction back: no partial write.
 */
class BusinessFunctionCoverageGuard
{
    /**
     * Validate the state the write has just produced (read fresh from the
     * database, not from possibly stale relations).
     *
     * @throws ValidationException
     */
    public function assertCovered(User $user): void
    {
        if (! $user->is_active || $user->isServiceAccount()) {
            return;
        }

        if ($user->businessFunctions()->exists()) {
            return;
        }

        throw ValidationException::withMessages([
            'employment.business_function_ids' => __('users.employment.business_function_required'),
        ]);
    }
}
