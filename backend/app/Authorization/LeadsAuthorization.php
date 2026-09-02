<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `leads` resource (spec 0024).
 *
 * No contextual rules: every field's ceiling is simply visible+editable when
 * the actor may write (create/update), else visible+readonly, mirroring
 * CampaignsAuthorization. `registry_id`/`campaign_id` are the mandatory
 * fields (BR-1, spec 0041 D-1); no `code` field exists for a Lead (D-3).
 *
 * Lead status is display-only and derived from assignment/opportunity state.
 *
 * Spec 0094, D-5: `products_of_interest` is OPTIONAL (unlike its Opportunity
 * counterpart) — a Lead is valid with zero products (AC-036), so it is not
 * `mandatory` here.
 */
class LeadsAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'leads';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('registry_id', 'select', mandatory: true),
            new FieldDefinition('campaign_id', 'select', mandatory: true),
            new FieldDefinition('operational_site_id', 'select'),
            new FieldDefinition('source_id', 'select'),
            new FieldDefinition('operator_id', 'select'),
            new FieldDefinition('notes', 'textarea'),
            new FieldDefinition('extra_fields', 'textarea'),
            new FieldDefinition('products_of_interest', 'multiselect'),
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
            'registry_id' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'campaign_id' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'operational_site_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'source_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'operator_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'notes' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'extra_fields' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'products_of_interest' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        /** @var Lead|null $model */
        return [
            'delete' => $model !== null && $actor->can('leads.delete'),
            'export' => $actor->can('leads.export'),
            'import' => $actor->can('leads.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `leads.view` boundary is enforced separately by
            // GET /api/activity-log/leads/{id} itself.
            'view_activity' => $model !== null && $actor->can('leads.viewActivity'),
        ];
    }
}
