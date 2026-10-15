<?php

namespace App\Tables\AgentFiscalProfiles;

use App\Enums\EnasarcoMandateType;
use App\Models\VatRate;
use App\Tables\Support\EditableColumns;

/**
 * Declarative column/filter/action catalogue for the `agent-fiscal-profiles`
 * domain (spec 0212, 0213): pure data. `referent`, `vat_rate` and `is_current` are DERIVED
 * (referent name; VAT rate label; "valid today"), handled by AgentFiscalProfilesTableDefinition.
 */
final class AgentFiscalProfileColumnCatalog
{
    /** Values of the derived `is_current` set filter. */
    public const string CURRENT_YES = 'yes';

    public const string CURRENT_NO = 'no';

    /**
     * The VAT rate as shown in the grid: "name (22%)", trailing zeros of the rate dropped.
     */
    public static function vatRateLabel(VatRate $vatRate): string
    {
        $rate = rtrim(rtrim(number_format((float) $vatRate->rate, 2, '.', ''), '0'), '.');

        return "{$vatRate->name} ({$rate}%)";
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return EditableColumns::apply([
            self::column('referent', 'text', sortable: true, filterType: 'set'),
            self::column('withholding_enabled', 'boolean', sortable: true, filterType: 'set'),
            self::column('withholding_rate', 'number', sortable: true, filterType: 'number'),
            self::column('withholding_base_percent', 'number', sortable: true, filterType: 'number'),
            self::column('enasarco_enabled', 'boolean', sortable: true, filterType: 'set'),
            self::column('enasarco_mandate_type', 'badge', sortable: true, filterType: 'set', options: EnasarcoMandateType::values()),
            self::column('vat_rate', 'text', sortable: true, filterType: 'set'),
            self::column('valid_from', 'date', sortable: true, filterType: 'date'),
            self::column('valid_until', 'date', sortable: true, filterType: 'date'),
            self::column('is_current', 'badge', sortable: false, filterType: 'set', options: [self::CURRENT_YES, self::CURRENT_NO]),
            self::column('updated_at', 'date', sortable: true, filterType: 'date'),
        ], [
            // Spec 0264, D-2: the switches and the agent stay on the record page.
            'valid_from' => [],
            'valid_until' => EditableColumns::NULLABLE,
            'withholding_rate' => [],
            'withholding_base_percent' => [],
            'enasarco_mandate_type' => ['options' => EnasarcoMandateType::values()],
            'vat_rate' => ['relation' => ['resource' => 'vat-rates'], 'editableField' => 'vat_rate_id', 'nullable' => true],
        ]);
    }

    /**
     * The editable columns a row takes a cell edit on (spec 0264, D-2): the
     * rates only while the withholding is on, the mandate type only while
     * ENASARCO is on.
     *
     * @return array<int, string>
     */
    public static function rowEditableColumnIds(bool $withholdingEnabled, bool $enasarcoEnabled): array
    {
        $switched = [
            'withholding_rate' => $withholdingEnabled,
            'withholding_base_percent' => $withholdingEnabled,
            'enasarco_mandate_type' => $enasarcoEnabled,
        ];

        return array_values(array_filter(
            array_column(array_filter(self::columns(), static fn (array $column): bool => ($column['editable'] ?? false) === true), 'id'),
            static fn (string $id): bool => $switched[$id] ?? true,
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns  the columns the actor can see
     * @return array<int, array<string, mixed>>
     */
    public static function filters(array $columns): array
    {
        return array_map(
            static fn (array $column): array => array_filter([
                'columnId' => $column['id'],
                'type' => $column['filterType'],
                'options' => $column['options'] ?? null,
            ], static fn (mixed $value): bool => $value !== null),
            array_values(array_filter($columns, static fn (array $column): bool => isset($column['filterType']))),
        );
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
                'permission' => 'agent-fiscal-profiles.view',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'agent-fiscal-profiles.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'agent-fiscal-profiles.viewActivity',
            ],
        ];
    }

    /**
     * @param  array<int, string>|null  $options
     * @return array<string, mixed>
     */
    private static function column(string $id, string $type, bool $sortable, string $filterType, ?array $options = null): array
    {
        return array_filter([
            'id' => $id,
            'label' => 'agentFiscalProfiles.columns.'.$id,
            'type' => $type,
            'visible' => true,
            'sortable' => $sortable,
            'filterable' => true,
            'filterType' => $filterType,
            'options' => $options,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
