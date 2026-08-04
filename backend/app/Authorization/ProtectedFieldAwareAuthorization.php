<?php

declare(strict_types=1);

namespace App\Authorization;

use App\FieldChangeRequests\ProtectedFieldRegistry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Decorator (spec 0078, D-1) that narrows a resource's field-permission
 * ceiling for its PROTECTED fields (config/field-change-requests.php): an
 * actor who lacks the field's dedicated permission
 * (`ProtectedField::permission()`, e.g. "request-management.updateSource")
 * sees that field as visible+readonly instead of whatever the inner
 * ResourceAuthorization computed. Only ever NARROWS — a field the inner
 * authorization already made HIDDEN stays hidden (proposing a change to
 * something the actor cannot even see would leak its existence).
 *
 * Applied by AuthorizationRegistry::resolve() exactly like
 * CustomFieldAwareAuthorization: its input is already the merged
 * ceiling+DB-matrix result (AbstractResourceAuthorization::fieldPermissions()
 * is FINAL) plus any custom fields, so this restriction is the LAST word.
 * Consistent with F-3: a `mandatory` field bypasses the DB-matrix intersect,
 * but it does not bypass this decorator — a distinct, orthogonal mechanism
 * (D-1) that this spec introduces without touching `mandatory` semantics.
 *
 * No special case for the super-admin: `Gate::before` (AppServiceProvider)
 * already makes `$actor->can()` unconditionally true for that role, so the
 * restriction below never triggers for them (AC-004) — the same
 * privileged-role bypass every other permission check in the app relies on.
 */
final class ProtectedFieldAwareAuthorization implements ResourceAuthorization
{
    public function __construct(
        private readonly ResourceAuthorization $inner,
        private readonly ProtectedFieldRegistry $registry,
    ) {}

    public function resource(): string
    {
        return $this->inner->resource();
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return $this->inner->fields();
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return $this->inner->actions();
    }

    /**
     * @return array<string, bool>
     */
    public function resourcePermissions(User $actor, ?Model $model): array
    {
        return $this->inner->resourcePermissions($actor, $model);
    }

    /**
     * @return array<string, FieldPermission>
     */
    public function fieldPermissions(User $actor, ?Model $model): array
    {
        $permissions = $this->inner->fieldPermissions($actor, $model);

        foreach ($this->registry->forResource($this->resource()) as $field => $protectedField) {
            if (! isset($permissions[$field]) || $permissions[$field]->hidden) {
                continue;
            }

            if (! $actor->can($protectedField->permission())) {
                // AC-006: propagate the original `required` so a mandatory
                // field stays required (visible+readonly, never optional).
                $permissions[$field] = FieldPermission::visibleReadonly(required: $permissions[$field]->required);
            }
        }

        return $permissions;
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return $this->inner->actionPermissions($actor, $model);
    }
}
