<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Enums\FinancialAccountType;
use App\Models\FinancialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `financial-accounts` resource (spec 0189).
 *
 * Every field's ceiling is visible+editable when the actor may write, else
 * visible+readonly — EXCEPT `type` (D-7), writable ONLY in create: once
 * persisted it is permanently readonly (UpdateFinancialAccountRequest rejects
 * a different value). `reveal_card_number` is offered only on a card.
 */
class FinancialAccountsAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'financial-accounts';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('type', 'select', mandatory: true),
            new FieldDefinition('name', 'text', mandatory: true),
            new FieldDefinition('company_id', 'select'),
            new FieldDefinition('iban', 'text'),
            new FieldDefinition('account_number', 'text'),
            new FieldDefinition('address_line', 'text'),
            new FieldDefinition('postal_code', 'text'),
            new FieldDefinition('country_id', 'select'),
            new FieldDefinition('state_id', 'select'),
            new FieldDefinition('province_id', 'select'),
            new FieldDefinition('city_id', 'select'),
            new FieldDefinition('card_type', 'select'),
            new FieldDefinition('card_circuit', 'select'),
            new FieldDefinition('linked_account_id', 'select'),
            new FieldDefinition('card_holder', 'text'),
            new FieldDefinition('card_number', 'text'),
            new FieldDefinition('card_expiry', 'text'),
            new FieldDefinition('notes', 'textarea'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['delete', 'export', 'view_activity', 'reveal_card_number'];
    }

    /**
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        $mayWrite = $this->actorMayWrite($actor, $model);
        $editable = $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly();
        $permissions = [];

        foreach ($this->fields() as $field) {
            $permissions[$field->key] = $editable;
        }

        $permissions['type'] = $mayWrite && $model === null ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly();
        $permissions['name'] = $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly();

        return $permissions;
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        $isCard = $model instanceof FinancialAccount && $model->type === FinancialAccountType::Card;

        return [
            'delete' => $model !== null && $actor->can('financial-accounts.delete'),
            'export' => $actor->can('financial-accounts.export'),
            'view_activity' => $model !== null && $actor->can('financial-accounts.viewActivity'),
            'reveal_card_number' => $isCard && $actor->can('financial-accounts.revealCardNumber'),
        ];
    }
}
