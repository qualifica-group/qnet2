<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `quotes` resource (spec 0065, MT-05).
 *
 * Every field's ceiling is visible+editable when the actor may write
 * (create/update), else visible+readonly — EXCEPT `code` and `opportunity_id`:
 *
 *  - `code` (D-13, same pattern as ProjectsAuthorization/spec 0025): writable
 *    only in create ($model === null); once persisted it is permanently
 *    readonly, so EnforcesFieldPermissions rejects a changed `code` on
 *    update with a 422 (AC-069).
 *  - `opportunity_id` (AC-025): immutable once the quote exists — readonly
 *    whenever $model !== null, regardless of write ability (the FormRequest
 *    layer additionally rejects it outright via `prohibited` on update).
 *
 * `offer_lines`/`cost_lines` (D-8/D-11) are the two repeatable line sets: an
 * empty array is a legitimate, authoritative full-replace (AC-036), so
 * neither is `mandatory`.
 */
class QuotesAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'quotes';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('code', 'text'),
            new FieldDefinition('title', 'text', mandatory: true),
            new FieldDefinition('opportunity_id', 'select', mandatory: true),
            new FieldDefinition('quote_status_id', 'select', mandatory: true),
            new FieldDefinition('commercial_id', 'select'),
            new FieldDefinition('reporter_id', 'select'),
            new FieldDefinition('supervisor_id', 'select'),
            new FieldDefinition('internal_notes', 'textarea'),
            new FieldDefinition('offer_lines', 'lines'),
            new FieldDefinition('cost_lines', 'lines'),
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

        return [
            // Writable only on create (spec 0025/D-13): permanently readonly
            // once a $model exists, regardless of write ability.
            'code' => $mayWrite && $model === null ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'title' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            // Immutable once persisted (AC-025): readonly whenever a $model
            // already exists, regardless of write ability.
            'opportunity_id' => $mayWrite && $model === null ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'quote_status_id' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'commercial_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'reporter_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'supervisor_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'internal_notes' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'offer_lines' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'cost_lines' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('quotes.delete'),
            'export' => $actor->can('quotes.export'),
            'import' => $actor->can('quotes.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `quotes.view` boundary is enforced separately by
            // GET /api/activity-log/quotes/{id} itself.
            'view_activity' => $model !== null && $actor->can('quotes.viewActivity'),
        ];
    }
}
