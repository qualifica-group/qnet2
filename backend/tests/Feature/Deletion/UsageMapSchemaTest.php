<?php

use App\Services\Deletion\UsageMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Spec 0384 AC-009: every foreign key pointing to a configuration table is
 * either a use that blocks the delete, a part the row owns, or covered by
 * the dedicated guard of that module. A new FK not declared here fails, so a
 * new module cannot silently reopen the "deleted although in use" gap.
 */
const DEDICATED_DELETE_GUARDS = [
    // QuoteWorkflowStatusUsageGuard / WorkOrderWorkflowStatusUsageGuard (spec 0377).
    'quote_workflows' => ['quote_workflow_criteria.quote_workflow_id', 'quote_workflow_statuses.quote_workflow_id'],
    'quote_workflow_statuses' => ['quotes.quote_workflow_status_id'],
    'work_order_workflows' => ['work_order_workflow_criteria.work_order_workflow_id', 'work_order_workflow_statuses.work_order_workflow_id'],
    'work_order_workflow_statuses' => [
        'tasks.work_order_workflow_status_id',
        'task_template_items.work_order_workflow_status_id',
        'work_orders.status_before_contract_termination_id',
        'work_orders.work_order_workflow_status_id',
    ],
    // TaskStatusService, TaskTemplateService, DocumentLayoutService.
    'task_statuses' => ['tasks.task_status_id', 'task_template_items.task_status_id'],
    'task_templates' => ['work_orders.task_template_id', 'task_template_items.task_template_id', 'task_template_stages.task_template_id'],
    'document_layouts' => ['invoices.layout_id', 'quotes.layout_id'],
    // ChartAccount::hasChildren(), TreasuryTypeService, CommissionConfigurationService, CampaignService.
    'chart_accounts' => ['chart_accounts.parent_id', 'cost_types.chart_account_id', 'revenue_types.chart_account_id', 'treasury_types.chart_account_id'],
    'treasury_types' => ['treasury_movements.treasury_type_id'],
    'commission_configurations' => ['quote_line_commissions.commission_configuration_id'],
    'campaigns' => ['leads.campaign_id', 'campaign_product_lines.campaign_id'],
];

/**
 * @return array<string, list<string>> configuration table => every declared `table.column`
 */
function declaredConfigurationReferences(): array
{
    $declared = DEDICATED_DELETE_GUARDS;
    foreach (UsageMap::USAGES as $table => $usages) {
        $declared[$table] = [...($declared[$table] ?? []), ...array_keys($usages), ...(UsageMap::OWNED[$table] ?? [])];
    }

    return $declared;
}

it('declares every foreign key pointing to a configuration table', function () {
    $declared = declaredConfigurationReferences();
    $undeclared = [];
    $checked = 0;

    foreach (Schema::getTables() as $table) {
        foreach (Schema::getForeignKeys($table['name']) as $foreignKey) {
            $target = $foreignKey['foreign_table'];
            $reference = $table['name'].'.'.$foreignKey['columns'][0];
            if (! array_key_exists($target, $declared)) {
                continue;
            }
            $checked++;
            if (! in_array($reference, $declared[$target], true)) {
                $undeclared[] = "{$target} <- {$reference}";
            }
        }
    }

    // Guards against a vacuous pass if the driver reported no foreign keys.
    expect($checked)->toBeGreaterThan(count(UsageMap::USAGES))
        ->and($undeclared)->toBe([]);
});

it('declares only columns that exist', function () {
    $missing = [];
    foreach (declaredConfigurationReferences() as $references) {
        foreach ($references as $reference) {
            [$table, $column] = explode('.', $reference, 2);
            if (! Schema::hasColumn($table, $column)) {
                $missing[] = $reference;
            }
        }
    }

    expect($missing)->toBe([]);
});

it('has a translated label for every use', function () {
    $labels = collect(UsageMap::USAGES)->flatten()->unique();

    foreach (['it', 'en'] as $locale) {
        $missing = $labels->reject(fn (string $label) => trans()->has("deletion.usages.{$label}", $locale, false));
        expect($missing->values()->all())->toBe([]);
    }
});
