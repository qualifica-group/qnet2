<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `work-orders` resource (spec 0093).
 *
 * Every field's ceiling is visible+editable when the actor may write
 * (create/update), else visible+readonly — EXCEPT `code`/`quote_id` (D-1/
 * D-5), writable ONLY in create ($model === null); once persisted both are
 * permanently readonly regardless of write ability, mirroring
 * UnitsOfMeasureAuthorization's own `code` ceiling. `status` does not appear
 * (D-3): it is never client-writable.
 *
 * Spec 0096: `start_date`/`supervisor_ids` ("Responsabili") are `required` —
 * a commessa has a start date and at least one responsabile — but plainly
 * editable after create, deliberately NOT create-only like `code`/`quote_id`.
 * `participant_slots` ("Partecipanti") is the optional team, the same
 * `multiselect` field type OpportunitiesAuthorization declares for its own
 * slot field.
 *
 * `attribute_values` (spec 0098): declared EXPLICITLY, same `custom` type and
 * visible/editable-when-may-write ceiling as `attribute_values` on
 * QuotesAuthorization — no contextual exception.
 */
class WorkOrdersAuthorization extends AbstractResourceAuthorization
{
    public function resource(): string
    {
        return 'work-orders';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('code', 'text'),
            new FieldDefinition('quote_id', 'select'),
            new FieldDefinition('title', 'text', mandatory: true),
            new FieldDefinition('type', 'select', mandatory: true),
            new FieldDefinition('start_date', 'date', mandatory: true),
            new FieldDefinition('supervisor_ids', 'multiselect', mandatory: true),
            new FieldDefinition('participant_slots', 'multiselect'),
            new FieldDefinition('callback_date', 'date'),
            new FieldDefinition('description', 'textarea'),
            new FieldDefinition('internal_notes', 'textarea'),
            new FieldDefinition('is_force_closed', 'select'),
            new FieldDefinition('force_close_reason', 'textarea'),
            new FieldDefinition('quote_line_ids', 'select'),
            new FieldDefinition('attribute_values', 'custom'),
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
        // code/quote_id (D-1/D-5): writable only in create, permanently
        // readonly once a $model exists, regardless of write ability — the
        // immutability itself is enforced ahead of this ceiling by
        // UpdateWorkOrderRequest's own `prohibited` rules.
        $mayWriteOnlyAtCreate = $mayWrite && $model === null;

        return [
            'code' => $mayWriteOnlyAtCreate ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'quote_id' => $mayWriteOnlyAtCreate ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(required: true),
            'title' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(required: true),
            'type' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(required: true),
            // Spec 0096, D-6: required, but plainly editable after create —
            // no create-only ceiling like code/quote_id.
            'start_date' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(required: true),
            'supervisor_ids' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(required: true),
            'participant_slots' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'callback_date' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'description' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'internal_notes' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'is_force_closed' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'force_close_reason' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'quote_line_ids' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'attribute_values' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('work-orders.delete'),
            'export' => $actor->can('work-orders.export'),
            'import' => $actor->can('work-orders.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `work-orders.view` boundary is enforced
            // separately by GET /api/activity-log/work-orders/{id}.
            'view_activity' => $model !== null && $actor->can('work-orders.viewActivity'),
        ];
    }
}
