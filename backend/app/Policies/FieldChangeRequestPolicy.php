<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Policies\Abstracts\BasePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Policy for the `field-change-requests` resource (spec 0078). A request is
 * immutable once handled: no `update`/`delete`/`import` ability exists for
 * this resource (abilities() restricts BasePolicy's default list), the only
 * addition on top is `manage` — the single ability that gates approve/reject
 * (D-7's own permission check happens separately, inside
 * TableCellUpdateService, when the approved value is actually applied).
 */
class FieldChangeRequestPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'field-change-requests';
    }

    /**
     * The richiedente (requester) may always read their OWN request even
     * without `field-change-requests.view` (AC-039) — proposing a change and
     * then being unable to check on it would be a broken loop. Every other
     * viewer still needs the permission.
     *
     * `Model` (not FieldChangeRequest) because PHP forbids NARROWING a
     * parameter type in an override: BasePolicy::view() declares `Model`, and
     * a concrete type here is a fatal error at class-declaration time, not a
     * mere static-analysis complaint. Same shape as the other BasePolicy
     * children that need their record (ImportRunPolicy, NotePolicy); the
     * policy only ever receives a FieldChangeRequest, the model it is
     * registered for.
     */
    public function view(User $user, Model $model): bool
    {
        return $user->can($this->permission('view'))
            || $user->id === $model->requested_by_id;
    }

    /**
     * The only ability that may approve or reject a pending request
     * (AC-032: not even the requester bypasses this with the manage
     * permission alone — applying the value on top still needs the field's
     * own write permission, enforced by TableCellUpdateService, D-7).
     */
    public function manage(User $user): bool
    {
        return $user->can($this->permission('manage'));
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['viewAny', 'view', 'create', 'manage', 'export', 'viewActivity'];
    }
}
