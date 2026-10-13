<?php

namespace App\Tables\CommissionAccruals;

use App\Enums\CommissionAccrualType;
use App\Enums\CommissionRecipientRole;

/**
 * Declarative column/filter catalogue of the `commission-accruals` domain
 * (spec 0212, 0213). Pure data; the columns tied to a permission field are mapped in
 * FIELD_BY_COLUMN so the definition can hide them per actor.
 */
final class CommissionAccrualColumnCatalog
{
    public const string LABEL_PREFIX = 'commissionAccruals.columns.';

    /** Recipient kinds: the morph aliases of a referent or a user. */
    public const array RECIPIENT_KINDS = ['referent', 'user'];

    /** Column id => `commission-accruals` field whose visibility gates it. */
    public const array FIELD_BY_COLUMN = [
        'base_amount' => 'base_amount',
        'amount' => 'amount',
    ];

    /**
     * The roles a movement can carry: the supplier commission never accrues (D-3).
     *
     * @return array<int, string>
     */
    public static function roles(): array
    {
        return array_values(array_diff(array_column(CommissionRecipientRole::cases(), 'value'), [CommissionRecipientRole::Supplier->value]));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            self::column('accrued_on', 'date', sortable: true, filter: 'date'),
            self::column('accrued_month', 'text', visible: false, filter: 'set'),
            self::column('recipient', 'text', sortable: true, filter: 'set'),
            self::column('recipient_kind', 'badge', sortable: true, filter: 'set', options: self::RECIPIENT_KINDS),
            self::column('recipient_role', 'badge', sortable: true, filter: 'set', options: self::roles()),
            self::column('type', 'badge', sortable: true, filter: 'set', options: CommissionAccrualType::values()),
            self::column('company', 'text', sortable: true, filter: 'set'),
            self::column('invoice', 'text', sortable: true, filter: 'text'),
            self::column('customer', 'text', sortable: true, filter: 'set'),
            self::column('quote', 'text', sortable: true, filter: 'text'),
            self::column('product', 'text', sortable: true, filter: 'text'),
            self::column('base_amount', 'number', sortable: true, filter: 'number'),
            self::column('amount', 'number', sortable: true, filter: 'number'),
            self::column('settlement', 'text', sortable: true, filter: 'text'),
            self::column('settled_externally_on', 'date', sortable: true, filter: 'date'),
            self::column('status', 'badge', filter: 'set', options: CommissionAccrualSql::STATUSES),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<int, array<string, mixed>>
     */
    public static function filters(array $columns): array
    {
        $filters = [];

        foreach ($columns as $column) {
            if (($column['filterable'] ?? false) !== true) {
                continue;
            }

            $filter = ['columnId' => $column['id'], 'type' => $column['filterType']];

            if (isset($column['options'])) {
                $filter['options'] = $column['options'];
            }

            $filters[] = $filter;
        }

        return $filters;
    }

    /**
     * @param  array<int, string>|null  $options
     * @return array<string, mixed>
     */
    private static function column(string $id, string $type, bool $visible = true, bool $sortable = false, ?string $filter = null, ?array $options = null): array
    {
        $column = [
            'id' => $id,
            'label' => self::LABEL_PREFIX.$id,
            'type' => $type,
            'visible' => $visible,
            'sortable' => $sortable,
            'filterable' => $filter !== null,
        ];

        if ($filter !== null) {
            $column['filterType'] = $filter;
        }

        if ($options !== null) {
            $column['options'] = $options;
        }

        return $column;
    }
}
