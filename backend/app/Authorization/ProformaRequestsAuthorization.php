<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `proforma-requests` resource (spec 0193). The
 * only editable field is `note` (mandatory, writable on create and update);
 * every other attribute is set by the service and never by the client.
 */
class ProformaRequestsAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'proforma-requests';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('note', 'textarea', mandatory: true),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['delete', 'export', 'view_activity'];
    }

    /**
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        return [
            'note' => $this->actorMayWrite($actor, $model)
                ? FieldPermission::visibleEditable(required: true)
                : FieldPermission::visibleReadonly(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('proforma-requests.delete'),
            'export' => $actor->can('proforma-requests.export'),
            'view_activity' => $model !== null && $actor->can('proforma-requests.viewActivity'),
        ];
    }
}
