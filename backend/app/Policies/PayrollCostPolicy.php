<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Abstracts\BasePolicy;

/**
 * Policy for the `payroll-costs` resource (spec 0430, D-12): the rows are
 * only read in the grid and written by the monthly import, never by a form.
 * Two permissions only: `view` (menu, grid) and `import`.
 */
class PayrollCostPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'payroll-costs';
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['view', 'import'];
    }

    /**
     * The grid is the listing: it is gated on `view`, there is no separate
     * `viewAny` permission.
     */
    public function viewAny(User $user): bool
    {
        return $user->can($this->permission('view'));
    }

    /**
     * Importing replaces a whole month, so it also requires seeing the rows.
     */
    public function import(User $user): bool
    {
        return $user->can($this->permission('view')) && $user->can($this->permission('import'));
    }
}
