<?php

namespace App\Policies;

use App\Models\TableFilterView;
use App\Models\User;
use App\Policies\Abstracts\BasePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Authorization for a saved TableFilterView (spec 0007, extended by spec
 * 0158): owner-only for update/delete, permission-backed for `publish`.
 *
 * List/create are gated by the table definition's own viewAny (enforced in
 * TableFilterViewController, same as every tables/{domain} endpoint).
 * update()/delete() stay ownership rules, not a permission string — a
 * `BasePolicy` default would let ANY holder of `table-filter-views.update`
 * edit another user's view, which is not the rule here. `publish` (spec
 * 0158, D-3) IS a real permission: `visibility: shared` on a POST/PUT is
 * gated on `table-filter-views.publish` (checked in the controller), so
 * `abilities()` is reduced to just that one — SyncPermissions derives the
 * catalog from THIS override (late static binding, BasePolicy::
 * permissions()), so only `table-filter-views.publish` is ever created,
 * never the 8 standard viewAny/view/create/update/delete/export/import/
 * viewActivity (none of which this resource's endpoints ever check). The
 * global super-admin bypass (Gate::before in AppServiceProvider) already
 * grants a super-admin actor every ability, so it is NOT duplicated here.
 */
class TableFilterViewPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'table-filter-views';
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['publish'];
    }

    public function publish(User $user): bool
    {
        return $user->can($this->permission('publish'));
    }

    public function update(User $user, Model $model): bool
    {
        return $model instanceof TableFilterView && $model->user_id === $user->id;
    }

    public function delete(User $user, Model $model): bool
    {
        return $model instanceof TableFilterView && $model->user_id === $user->id;
    }
}
