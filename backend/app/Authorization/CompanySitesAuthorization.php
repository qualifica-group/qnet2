<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `company-sites` resource (spec 0020).
 *
 * The former "Altro" section AND the client-specific ERP settings
 * (responsible_*, proforma/invoice progressives, quotation_*) are gone: those
 * attributes are now universal custom fields (spec 0021, QualificaTemplateSeeder),
 * authorized generically by the custom-fields layer — not listed here. Every
 * native field's ceiling is the usual visible+editable-when-may-write /
 * visible+readonly-otherwise (mirrors CompaniesAuthorization).
 */
class CompanySitesAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'company-sites';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        $fields = [
            new FieldDefinition('name', 'text', 'profile', mandatory: true),
            new FieldDefinition('notes', 'textarea', 'profile'),
            new FieldDefinition('logo', 'image', 'profile'),
            // The nested personal-data card (contacts + address), mirroring
            // RegistriesAuthorization verbatim (dot-path shape of the write
            // payload, spec 0008). `type` is always `company` here, but the
            // catalogue keeps the full key set in lockstep with the shared
            // ValidatesUserProfile surface, exactly like Registry.
            new FieldDefinition('personal_data.type', 'select', 'personal_data'),
            new FieldDefinition('personal_data.company_name', 'text', 'personal_data'),
            new FieldDefinition('personal_data.tax_code', 'text', 'personal_data'),
            new FieldDefinition('personal_data.vat_number', 'text', 'personal_data'),
            new FieldDefinition('personal_data.sdi_code', 'text', 'personal_data'),
            new FieldDefinition('personal_data.contacts', 'collection', 'personal_data'),
            new FieldDefinition('personal_data.addresses', 'collection', 'personal_data'),
            new FieldDefinition('company_id', 'select', 'settings'),
            new FieldDefinition('banks', 'collection', 'banks'),
        ];

        return $fields;
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['delete', 'export', 'import', 'upload_logo', 'delete_logo', 'set_default', 'view_activity'];
    }

    /**
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        $ceiling = [
            'name' => $this->writableOrReadonly($actor, $model, required: true),
            'notes' => $this->writableOrReadonly($actor, $model),
            'logo' => $this->writableOrReadonly($actor, $model),
            'personal_data.type' => $this->writableOrReadonly($actor, $model),
            'personal_data.company_name' => $this->writableOrReadonly($actor, $model),
            'personal_data.tax_code' => $this->writableOrReadonly($actor, $model),
            'personal_data.vat_number' => $this->writableOrReadonly($actor, $model),
            'personal_data.sdi_code' => $this->writableOrReadonly($actor, $model),
            'personal_data.contacts' => $this->writableOrReadonly($actor, $model),
            'personal_data.addresses' => $this->writableOrReadonly($actor, $model),
            'company_id' => $this->writableOrReadonly($actor, $model),
            'banks' => $this->writableOrReadonly($actor, $model),
        ];

        return $ceiling;
    }

    private function writableOrReadonly(User $actor, ?Model $model, bool $required = false): FieldPermission
    {
        return $this->actorMayWrite($actor, $model)
            ? FieldPermission::visibleEditable(required: $required)
            : FieldPermission::visibleReadonly();
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $actor->can('company-sites.delete'),
            'export' => $actor->can('company-sites.export'),
            'import' => $actor->can('company-sites.import'),
            // Logo/set-default are gated by the resource's own `update` ability,
            // mirroring UsersAuthorization::actionPermissions (upload_avatar).
            'upload_logo' => $model !== null && $actor->can('company-sites.update'),
            'delete_logo' => $model !== null && $actor->can('company-sites.update'),
            'set_default' => $model !== null && $actor->can('company-sites.update'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `company-sites.view` boundary is enforced
            // separately by GET /api/activity-log/company-sites/{id}.
            'view_activity' => $model !== null && $actor->can('company-sites.viewActivity'),
        ];
    }
}
