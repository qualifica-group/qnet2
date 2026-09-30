<?php

namespace App\Tables\Users;

use App\Enums\LocaleEnum;
use App\Enums\QualificationTypeEnum;
use App\Enums\RelationshipTypeEnum;

/**
 * Declarative column/filter/action catalogue for the `users` domain.
 *
 * Extracted out of UsersTableDefinition (file-size split, engineering.md §6):
 * pure data (no logic), so it carries the same declarative shapes the
 * generic Table framework consumes (see AbstractTableDefinition::columns())
 * without inflating the definition class that owns the actual behavior.
 */
final class UserColumnCatalog
{
    /**
     * @param  array<int, string>  $userTypeValues
     * @return array<int, array<string, mixed>>
     */
    public static function columns(array $userTypeValues): array
    {
        return [
            [
                'id' => 'id',
                'label' => 'users.columns.id',
                'type' => 'number',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                // Avatar, embedded inline as a data: URI; the frontend renders
                // it via a custom avatar cell (not sortable nor filterable — it
                // is a derived value, not a real column).
                'id' => 'avatar_url',
                'label' => 'users.columns.avatar',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
                'filterType' => null,
                // Narrow, fixed default: the cell only holds a small avatar.
                'width' => 56,
            ],
            [
                'id' => 'name',
                'label' => 'users.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                // Global quick-search spans this real column (spec 0009).
                'searchable' => true,
            ],
            [
                'id' => 'email',
                'label' => 'users.columns.email',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            [
                'id' => 'roles',
                'label' => 'users.columns.roles',
                'type' => 'tags',
                'visible' => true,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'locale',
                'label' => 'users.columns.locale',
                'type' => 'enum',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
                'options' => LocaleEnum::values(),
            ],
            [
                // Real boolean column: an inactive user cannot log in
                // (AuthService::login). Generic engine owns sort/set-filter/
                // distinct — mirrors business-functions' is_business_unit.
                'id' => 'is_active',
                'label' => 'users.columns.is_active',
                'type' => 'boolean',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'created_at',
                'label' => 'users.columns.created_at',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            [
                // Person vs company, derived from personalData.type. Rendered as a
                // BADGE whose label/color/icon come entirely from the enum (see
                // UsersTableDefinition::badgesFor). Sortable via a correlated
                // subquery (applyDerivedSort).
                'id' => 'user_type',
                'label' => 'users.columns.user_type',
                'type' => 'badge',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
                'options' => $userTypeValues,
            ],
            [
                // Pre-formatted primary address string (is_primary), derived from
                // personalData.addresses. Text filter via whereHas LIKE; sorted by
                // the primary address line via a correlated subquery. COMPUTED
                // (no real DB column) AND conditions-only by deliberate UX
                // decision (spec 0005): a formatted address string has no clean
                // single-column match, so hasFilterValues=false — no Set/
                // checklist, no `multi` widget, no /values call for this column.
                'id' => 'primary_address',
                'label' => 'users.columns.primary_address',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'hasFilterValues' => false,
            ],
            [
                // Geo name from the primary address. Hidden by default; set filter
                // with backend-resolved options (distinct names in use). Sorted by
                // the related geo name via a correlated subquery.
                'id' => 'country',
                'label' => 'users.columns.country',
                'type' => 'text',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'region',
                'label' => 'users.columns.region',
                'type' => 'text',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'province',
                'label' => 'users.columns.province',
                'type' => 'text',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'city',
                'label' => 'users.columns.city',
                'type' => 'text',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                // ALL primary contacts (one per type: phone, email, …) derived
                // from personalData.contacts where is_primary. Rendered as tags
                // (an array, like roles); the text filter matches ANY primary
                // contact of ANY type via whereHas LIKE. Sorted by the first
                // primary contact value (MIN) via a correlated subquery.
                // COMPUTED (no real DB column), Set Filter list resolved from
                // the distinct contact values (see UserPersonalDataColumns::
                // contactDistinctValues). hasFilterValues defaults to true.
                'id' => 'primary_contact',
                'label' => 'users.columns.primary_contact',
                'type' => 'tags',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
            ],
            [
                // Employment profile (spec 0015), derived from employment.
                // Related NAME columns (no static options, spec 0004/0005
                // Set Filter resolved dynamically via distinctValues), mirror
                // BusinessFunctionColumnCatalog's `manager`.
                'id' => 'business_function',
                'label' => 'users.columns.business_function',
                'type' => 'text',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'company',
                'label' => 'users.columns.company',
                'type' => 'text',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                // Formatted "line1[- city]" (EmploymentResource label contract).
                // CONDITIONS-ONLY, mirrors `primary_address` (spec 0005 UX
                // decision): a composed address string has no clean single-
                // column match.
                'id' => 'operational_site',
                'label' => 'users.columns.operational_site',
                'type' => 'text',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'hasFilterValues' => false,
            ],
            [
                'id' => 'relationship_type',
                'label' => 'users.columns.relationship_type',
                'type' => 'enum',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
                'options' => RelationshipTypeEnum::values(),
            ],
            [
                'id' => 'qualification_type',
                'label' => 'users.columns.qualification_type',
                'type' => 'enum',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
                'options' => QualificationTypeEnum::values(),
            ],
            [
                'id' => 'is_manager',
                'label' => 'users.columns.is_manager',
                'type' => 'boolean',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'reports_to',
                'label' => 'users.columns.reports_to',
                'type' => 'text',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                // No real DB column (derived from employment): an enumerated
                // checklist of exact hire dates is not a useful UX either, so
                // this is CONDITIONS-ONLY like `operational_site`/`primary_address`.
                'id' => 'hired_at',
                'label' => 'users.columns.hired_at',
                'type' => 'date',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
                'hasFilterValues' => false,
            ],
            [
                'id' => 'terminated_at',
                'label' => 'users.columns.terminated_at',
                'type' => 'date',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
                'hasFilterValues' => false,
            ],
        ];
    }

    /**
     * @param  array<int, string>  $userTypeValues
     * @return array<int, array<string, mixed>>
     */
    public static function filters(array $userTypeValues): array
    {
        return [
            ['columnId' => 'name', 'type' => 'text'],
            ['columnId' => 'email', 'type' => 'text'],
            ['columnId' => 'roles', 'type' => 'set'],
            ['columnId' => 'locale', 'type' => 'set', 'options' => LocaleEnum::values()],
            ['columnId' => 'is_active', 'type' => 'set'],
            ['columnId' => 'created_at', 'type' => 'date'],
            ['columnId' => 'user_type', 'type' => 'set', 'options' => $userTypeValues],
            ['columnId' => 'primary_address', 'type' => 'text'],
            // Geo set filters: options resolved dynamically in optionsFor().
            ['columnId' => 'country', 'type' => 'set'],
            ['columnId' => 'region', 'type' => 'set'],
            ['columnId' => 'province', 'type' => 'set'],
            ['columnId' => 'city', 'type' => 'set'],
            ['columnId' => 'primary_contact', 'type' => 'text'],
            ['columnId' => 'business_function', 'type' => 'set'],
            ['columnId' => 'company', 'type' => 'set'],
            ['columnId' => 'operational_site', 'type' => 'text'],
            ['columnId' => 'relationship_type', 'type' => 'set', 'options' => RelationshipTypeEnum::values()],
            ['columnId' => 'qualification_type', 'type' => 'set', 'options' => QualificationTypeEnum::values()],
            ['columnId' => 'is_manager', 'type' => 'set'],
            ['columnId' => 'reports_to', 'type' => 'set'],
            ['columnId' => 'hired_at', 'type' => 'date'],
            ['columnId' => 'terminated_at', 'type' => 'date'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function actions(): array
    {
        return [
            [
                'key' => 'view',
                'label' => 'actions.view',
                'icon' => 'eye',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'users.view',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'users.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'users.viewActivity',
            ],
            [
                // "Login as customer" impersonation (spec 0050): confirmed
                // because it swaps the actor's identity for the whole session.
                'key' => 'impersonate',
                'label' => 'actions.impersonate',
                'icon' => 'venetian-mask',
                'type' => 'action',
                'confirm' => true,
                'permission' => 'users.impersonate',
            ],
        ];
    }
}
