<?php

namespace App\Imports\RequestManagement;

/**
 * Static catalogue backing RequestManagementImportDefinition::fields()/
 * globalConfig() (spec 0348): the Registry fields the leads import maps, plus
 * the request-only ones. `label` values are i18n KEYS
 * (`imports.request-management.fields.*` / `.global.*`).
 *
 * ORDER MATTERS to ColumnMapper: when two fields claim the same header, the
 * first listed wins. `workflow_status` precedes `country` because "Stato" is
 * an alias of both, and in a trattative file it is the working status.
 */
final class RequestImportFieldCatalog
{
    /** @var array<string, array{group: string, type: string}> */
    private const array FIELD_CATALOG = [
        'full_name' => ['group' => 'identity', 'type' => 'text'],
        'first_name' => ['group' => 'identity', 'type' => 'text'],
        'last_name' => ['group' => 'identity', 'type' => 'text'],
        'company_name' => ['group' => 'identity', 'type' => 'text'],
        'tax_code' => ['group' => 'identity', 'type' => 'text'],
        'vat_number' => ['group' => 'identity', 'type' => 'text'],
        'email' => ['group' => 'contact', 'type' => 'text'],
        'phone' => ['group' => 'contact', 'type' => 'text'],
        'operator_name' => ['group' => 'request', 'type' => 'text'],
        'reporter_name' => ['group' => 'request', 'type' => 'text'],
        'workflow_status' => ['group' => 'request', 'type' => 'text'],
        'source_name' => ['group' => 'request', 'type' => 'text'],
        'next_callback_date' => ['group' => 'request', 'type' => 'date'],
        'product_name' => ['group' => 'request', 'type' => 'text'],
        'product_category_name' => ['group' => 'request', 'type' => 'text'],
        'street' => ['group' => 'address', 'type' => 'text'],
        'postal_code' => ['group' => 'address', 'type' => 'text'],
        'country' => ['group' => 'address', 'type' => 'text'],
        'region' => ['group' => 'address', 'type' => 'text'],
        'province' => ['group' => 'address', 'type' => 'text'],
        'city' => ['group' => 'address', 'type' => 'text'],
        'notes' => ['group' => 'request', 'type' => 'textarea'],
    ];

    /**
     * Fields that only feed a lookup and are not persisted as typed: the review
     * grid edits their outcome (operator, product, status), not the raw text.
     *
     * @var array<int, string>
     */
    private const array INPUT_ONLY = ['full_name', 'operator_name', 'reporter_name', 'workflow_status', 'source_name', 'product_name', 'product_category_name', 'next_callback_date'];

    /** @var array<int, array{id: string, required: bool, for_select_resource: string}> */
    private const array GLOBAL_FIELDS = [
        ['id' => 'product_category_id', 'required' => true, 'for_select_resource' => 'product-categories'],
        ['id' => 'operational_site_id', 'required' => false, 'for_select_resource' => 'operational-sites'],
        ['id' => 'source_id', 'required' => true, 'for_select_resource' => 'sources'],
        ['id' => 'operator_id', 'required' => false, 'for_select_resource' => 'users'],
    ];

    /**
     * @return array<int, array{id: string, required: bool}>
     */
    public function columns(): array
    {
        return array_map(static fn (string $id): array => ['id' => $id, 'required' => false], array_keys(self::FIELD_CATALOG));
    }

    /**
     * @return array<int, array{id: string, label: string, required: bool, group: ?string, type: string}>
     */
    public function fields(): array
    {
        return array_map(
            static fn (string $id, array $meta): array => [
                'id' => $id,
                'label' => "imports.request-management.fields.{$id}",
                'required' => false,
                'group' => $meta['group'],
                'type' => $meta['type'],
            ],
            array_keys(self::FIELD_CATALOG),
            self::FIELD_CATALOG,
        );
    }

    /**
     * @return array<int, array{id: string, label: string, required: bool, for_select_resource: ?string, multiple: bool, depends_on: ?string, default: mixed, required_unless_mapped: ?string}>
     */
    public function globalConfig(): array
    {
        return array_map(
            static fn (array $field): array => [
                'id' => $field['id'],
                'label' => "imports.request-management.global.{$field['id']}",
                'required' => $field['required'],
                'for_select_resource' => $field['for_select_resource'],
                'multiple' => false,
                'depends_on' => null,
                'default' => null,
                'required_unless_mapped' => null,
            ],
            self::GLOBAL_FIELDS,
        );
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    public function reviewFields(): array
    {
        return array_values(array_map(
            static fn (array $field): array => ['id' => $field['id'], 'label' => $field['label']],
            array_filter($this->fields(), static fn (array $field): bool => ! in_array($field['id'], self::INPUT_ONLY, true)),
        ));
    }
}
