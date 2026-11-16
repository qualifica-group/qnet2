<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization of the `quote-system-statuses` resource (spec 0404
 * part A): visible+editable for a writer, read-only otherwise. The fixed
 * behavior of a system row is a business rule (QuoteSystemStatusService),
 * not a field permission.
 */
class QuoteSystemStatusesAuthorization extends AbstractResourceAuthorization
{
    private const string RESOURCE = 'quote-system-statuses';

    public function resource(): string
    {
        return self::RESOURCE;
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('name', 'text', mandatory: true),
            new FieldDefinition('color', 'color', mandatory: true),
            new FieldDefinition('behavior', 'select', mandatory: true),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['delete', 'export', 'import', 'view_activity'];
    }

    /**
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        $mayWrite = $this->actorMayWrite($actor, $model);
        $field = static fn (): FieldPermission => $mayWrite
            ? FieldPermission::visibleEditable(required: true)
            : FieldPermission::visibleReadonly();

        return ['name' => $field(), 'color' => $field(), 'behavior' => $field()];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can(self::RESOURCE.'.delete'),
            'export' => $actor->can(self::RESOURCE.'.export'),
            'import' => $actor->can(self::RESOURCE.'.import'),
            'view_activity' => $model !== null && $actor->can(self::RESOURCE.'.viewActivity'),
        ];
    }
}
