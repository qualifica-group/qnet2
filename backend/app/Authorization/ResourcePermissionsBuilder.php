<?php

declare(strict_types=1);

namespace App\Authorization;

use App\FieldChangeRequests\ProtectedFieldRegistry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Serializes a ResourceAuthorization's three permission maps into the frozen
 * `permissions` wire shape (spec 0004): `{ resource, fields, actions }`, plus
 * `change_requestable_fields` (spec 0078): the protected fields the actor
 * cannot write directly but may propose a change for.
 */
final class ResourcePermissionsBuilder
{
    public function __construct(private readonly ProtectedFieldRegistry $protectedFields) {}

    /**
     * @return array{resource: array<string, bool>, fields: array<string, array<string, bool>>, actions: array<string, bool>, change_requestable_fields: array<int, string>}
     */
    public function build(ResourceAuthorization $authorization, User $actor, ?Model $model): array
    {
        $fieldPermissions = $authorization->fieldPermissions($actor, $model);

        return [
            'resource' => $authorization->resourcePermissions($actor, $model),
            'fields' => array_map(
                static fn (FieldPermission $permission): array => $permission->toArray(),
                $fieldPermissions,
            ),
            'actions' => $authorization->actionPermissions($actor, $model),
            'change_requestable_fields' => $this->changeRequestableFields($authorization, $actor, $fieldPermissions),
        ];
    }

    /**
     * A protected field belongs in this list when it is visible to $actor
     * (a hidden field cannot be proposed for change, see
     * ProtectedFieldAwareAuthorization) AND $actor lacks its dedicated
     * permission. Never `null`/absent (D-1): an empty array when the
     * resource declares no protected fields.
     *
     * @param  array<string, FieldPermission>  $fieldPermissions
     * @return array<int, string>
     */
    private function changeRequestableFields(ResourceAuthorization $authorization, User $actor, array $fieldPermissions): array
    {
        $fields = [];

        foreach ($this->protectedFields->forResource($authorization->resource()) as $field => $protectedField) {
            $permission = $fieldPermissions[$field] ?? null;

            if ($permission === null || $permission->hidden || $actor->can($protectedField->permission())) {
                continue;
            }

            $fields[] = $field;
        }

        return $fields;
    }
}
