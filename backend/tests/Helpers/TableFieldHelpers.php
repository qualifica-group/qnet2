<?php

declare(strict_types=1);

use App\Models\CustomFieldDefinition;

if (! function_exists('inspectionTableConfig')) {
    /**
     * The "ISO inspections" table of spec 0180: 5 columns + selectable + summary.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function inspectionTableConfig(array $overrides = []): array
    {
        return [...[
            'columns' => [
                ['key' => 'inspection_date', 'label' => 'Date', 'type' => 'date', 'required' => true],
                ['key' => 'inspector', 'label' => 'Inspector', 'type' => 'text'],
                ['key' => 'site', 'label' => 'Site', 'type' => 'enum', 'options' => [
                    ['value' => 'on_site', 'label' => 'On site'],
                    ['value' => 'off_site', 'label' => 'Off site'],
                ]],
                ['key' => 'findings', 'label' => 'Findings', 'type' => 'integer', 'config' => ['min' => 0, 'max' => 10]],
                ['key' => 'alert_sent', 'label' => 'Alert sent', 'type' => 'boolean'],
            ],
            'selectable' => ['key' => 'active', 'label' => 'Active'],
            'summary' => ['column' => 'inspection_date', 'strategy' => 'selected'],
        ], ...$overrides];
    }
}

if (! function_exists('tableCustomField')) {
    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $overrides
     */
    function tableCustomField(array $config = [], array $overrides = []): CustomFieldDefinition
    {
        return CustomFieldDefinition::factory()->forEntity('companies')->ofType('table')->create([
            ...['key' => 'inspections', 'config' => inspectionTableConfig($config)],
            ...$overrides,
        ]);
    }
}
