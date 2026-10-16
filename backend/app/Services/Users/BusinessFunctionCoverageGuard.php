<?php

namespace App\Services\Users;

use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * The mandatory business function rule (spec 0271 D-6): an active, non-service-account person must
 * cover at least one business function, i.e. own at least one
 * `employment.product_lines` row, unless the profile covers every category
 * (`covers_all_product_categories`, assumption A-1).
 *
 * Checked by UserService inside the write transaction, only when the request
 * touched `employment` or turned the user active (or on create), so an update
 * that touches neither never blocks existing legacy data (AC-011). The
 * exception rolls the whole transaction back: no partial write.
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

        $employment = $user->employment()->first();

        if ($employment !== null && ($employment->covers_all_product_categories || $employment->productLines()->exists())) {
            return;
        }

        throw ValidationException::withMessages([
            'employment.product_lines' => __('users.employment.business_function_required'),
        ]);
    }
}
