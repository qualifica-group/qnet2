<?php

namespace App\Http\Resources;

use App\Models\Role;
use App\Models\RoleFieldPermission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'permissions' => $this->getPermissionNames(),
            'users' => $this->memberIds(),
            // Flat field-permission matrix rows (spec 0006), consumed by the
            // Role form's "Permessi campi" section.
            'field_permissions' => $this->fieldPermissionsList(),
            'created_at' => $this->created_at,
        ];
    }

    /**
     * Ids of the users currently assigned to this role (Spatie model_has_roles
     * pivot), so the frontend Role form can pre-select members in edit mode.
     *
     * Resolved with a single id-only pivot query against the relation on the
     * populated model. We deliberately do NOT eager-load via `$role->load('users')`:
     * Spatie's `Role::users()` derives the related model from the model instance's
     * `guard_name`, but Eloquent's eager-load builds the relation on a bare Role
     * (no `guard_name`), so it falls back to the request's default guard — `sanctum`
     * on an authenticated API call — for which no provider model is configured,
     * crashing with "Class name must be a valid object or a string". Calling
     * `$this->users()` on the loaded role (guard_name = `web`) resolves correctly
     * and, as RoleResource only ever projects a single role, this is one query — no
     * N+1.
     *
     * Plain member ids are returned for any role the caller is already authorized
     * to view (`roles.view`); no super-admin membership rule is leaked here — the
     * privilege guards (ADR 0011) gate membership writes, not this read projection.
     *
     * @return array<int, int>
     */
    private function memberIds(): array
    {
        // The technical users of the API clients (spec 0210) are not listed.
        $ids = $this->relationLoaded('users')
            ? $this->users->reject->isServiceAccount()->pluck('id')
            : $this->users()->excludingServiceAccounts()->pluck('users.id');

        return $ids->map(static fn ($id): int => (int) $id)->values()->all();
    }

    /**
     * This role's field-permission matrix rows (spec 0006), flattened to
     * `{ resource, field, visible, editable, required }`. RoleResource only
     * ever projects a single role, so this is at most one extra query — no
     * N+1 — when the relation is not already eager-loaded.
     *
     * @return array<int, array{resource: string, field: string, visible: bool, editable: bool, required: bool}>
     */
    private function fieldPermissionsList(): array
    {
        $rows = $this->relationLoaded('fieldPermissions')
            ? $this->fieldPermissions
            : $this->fieldPermissions()->get();

        return $rows
            ->map(static fn (RoleFieldPermission $row): array => [
                'resource' => $row->resource,
                'field' => $row->field,
                'visible' => $row->visible,
                'editable' => $row->editable,
                'required' => $row->required,
            ])
            ->values()
            ->all();
    }
}
