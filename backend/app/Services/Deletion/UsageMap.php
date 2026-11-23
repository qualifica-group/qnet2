<?php

declare(strict_types=1);

namespace App\Services\Deletion;

/**
 * Where each configuration entity is used (spec 0384). A row of a table in
 * USAGES cannot be deleted while any `table.column` listed for it points to
 * it; the value is the `deletion.usages.*` label of that use. OWNED lists the
 * references that belong to the entity itself and go away with it (its
 * options, its own pivots), so they never block. Every foreign key pointing
 * to a table listed here must appear in one of the two lists: the schema test
 * fails otherwise, so a new module cannot silently reopen the gap.
 */
final class UsageMap
{
    public const array USAGES = [
        'absence_types' => [
            'absence_allowances.absence_type_id' => 'absence_allowances',
            'absence_requests.absence_type_id' => 'absence_requests',
            'company_closures.absence_type_id' => 'company_closures',
        ],
        'business_functions' => [
            'business_functions.parent_id' => 'sub_items',
            'business_function_user.business_function_id' => 'users',
            'campaign_product_lines.business_function_id' => 'campaign_product_lines',
            'employment_product_lines.business_function_id' => 'employment_product_lines',
            'opportunity_product_lines.business_function_id' => 'opportunity_product_lines',
            'project_product_lines.business_function_id' => 'project_product_lines',
            'product_categories.business_function_id' => 'product_categories',
            'purchase_requests.business_function_id' => 'purchase_requests',
        ],
        'company_closures' => [],
        'contract_statuses' => [
            'contracts.contract_status_id' => 'contracts',
            'contracts.status_before_suspension_id' => 'contracts',
        ],
        'contract_types' => [
            'employment_contracts.contract_type_id' => 'employment_contracts',
            'employment_profiles.contract_type_id' => 'employment_profiles',
        ],
        'cost_types' => [
            'agent_fiscal_profiles.cost_type_id' => 'agent_fiscal_profiles',
            'products.cost_type_id' => 'products',
            'purchase_invoices.cost_type_id' => 'purchase_invoices',
            'purchase_invoice_lines.cost_type_id' => 'purchase_invoice_lines',
            'purchase_order_lines.cost_type_id' => 'purchase_order_lines',
            'treasury_movements.cost_type_id' => 'treasury_movements',
            'work_order_costs.cost_type_id' => 'work_order_costs',
        ],
        'document_bundles' => [],
        'email_templates' => [
            'outbound_emails.email_template_id' => 'outbound_emails',
        ],
        'financial_accounts' => [
            'financial_accounts.linked_account_id' => 'linked_cards',
            'bank_reconciliations.financial_account_id' => 'bank_reconciliations',
            'invoices.financial_account_id' => 'invoices',
            'invoice_installments.financial_account_id' => 'invoice_installments',
            'purchase_invoices.financial_account_id' => 'purchase_invoices',
            'purchase_invoice_installments.financial_account_id' => 'purchase_invoice_installments',
            'treasury_movements.financial_account_id' => 'treasury_movements',
            'treasury_movements.counterpart_account_id' => 'treasury_movements',
        ],
        'invoice_tags' => [
            'invoices.invoice_tag_id' => 'invoices',
        ],
        'job_activities' => [
            'job_activity_job_role.job_activity_id' => 'job_roles',
        ],
        'job_roles' => [
            'employment_profile_job_role.job_role_id' => 'employment_profiles',
        ],
        'operational_sites' => [
            'business_function_operational_site.operational_site_id' => 'business_functions',
            'campaigns.operational_site_id' => 'campaigns',
            'company_closure_operational_site.operational_site_id' => 'company_closures',
            'employment_profile_operational_site.operational_site_id' => 'employment_profiles',
            'import_run_rows.operational_site_id' => 'import_run_rows',
            'leads.operational_site_id' => 'leads',
            'opportunities.operational_site_id' => 'opportunities',
            'opportunities.transferred_from_operational_site_id' => 'opportunities',
            'projects.operational_site_id' => 'projects',
            'purchase_invoices.operational_site_id' => 'purchase_invoices',
            'purchase_orders.operational_site_id' => 'purchase_orders',
            'purchase_requests.operational_site_id' => 'purchase_requests',
            'quotes.operational_site_id' => 'quotes',
            'quotes.transferred_from_operational_site_id' => 'quotes',
            'stock_balances.operational_site_id' => 'stock_balances',
            'stock_movements.from_operational_site_id' => 'stock_movements',
            'stock_movements.to_operational_site_id' => 'stock_movements',
        ],
        'payment_methods' => [
            'agent_fiscal_profiles.payment_method_id' => 'agent_fiscal_profiles',
            'invoices.payment_method_id' => 'invoices',
            'proforma_requests.payment_method_id' => 'proforma_requests',
            'purchase_invoices.payment_method_id' => 'purchase_invoices',
            'purchase_orders.payment_method_id' => 'purchase_orders',
            'quotes.payment_method_id' => 'quotes',
        ],
        'pipeline_statuses' => [
            'campaigns.pipeline_status_id' => 'campaigns',
            'projects.pipeline_status_id' => 'projects',
        ],
        'product_categories' => [
            'product_categories.parent_id' => 'sub_items',
            'product_categories.current_category_id' => 'legacy_categories',
            'products.category_id' => 'products',
            'campaign_product_lines.product_category_id' => 'campaign_product_lines',
            'commission_configurations.product_category_id' => 'commission_configurations',
            'employment_product_lines.product_category_id' => 'employment_product_lines',
            'opportunity_product_lines.product_category_id' => 'opportunity_product_lines',
            'project_product_lines.product_category_id' => 'project_product_lines',
        ],
        'product_typologies' => [
            'products.product_typology_id' => 'products',
        ],
        'purchase_request_priorities' => [
            'purchase_requests.purchase_request_priority_id' => 'purchase_requests',
        ],
        'referent_types' => [
            'referents.referent_type_id' => 'referents',
        ],
        'revenue_types' => [
            'invoice_lines.revenue_type_id' => 'invoice_lines',
            'products.revenue_type_id' => 'products',
            'product_categories.revenue_type_id' => 'product_categories',
            'treasury_movements.revenue_type_id' => 'treasury_movements',
        ],
        'reward_statuses' => [
            'rewards.reward_status_id' => 'rewards',
        ],
        'reward_types' => [
            'rewards.reward_type_id' => 'rewards',
        ],
        'roles' => [
            'model_has_roles.role_id' => 'users',
        ],
        'sectors' => [
            'sectors.parent_id' => 'sub_items',
            'sector_registry.sector_id' => 'registries',
            'purchase_order_sector.sector_id' => 'purchase_orders',
        ],
        'skills' => [
            'job_role_skill.skill_id' => 'job_roles',
            'user_skills.skill_id' => 'people',
        ],
        'sources' => [
            'campaigns.source_id' => 'campaigns',
            'leads.source_id' => 'leads',
            'quotes.source_id' => 'quotes',
            'registries.source_id' => 'registries',
        ],
        'staff_courses' => [
            'job_role_staff_course.staff_course_id' => 'job_roles',
            'staff_trainings.staff_course_id' => 'staff_trainings',
        ],
        'support_topics' => [],
        'tags' => [],
        'task_categories' => [
            'task_categories.parent_id' => 'sub_items',
            'support_topics.task_category_id' => 'support_topics',
            'tasks.task_category_id' => 'tasks',
        ],
        'task_importances' => [
            'support_topics.task_importance_id' => 'support_topics',
            'tasks.task_importance_id' => 'tasks',
        ],
        'task_priorities' => [
            'support_topics.task_priority_id' => 'support_topics',
            'support_topics.urgent_task_priority_id' => 'support_topics',
            'tasks.task_priority_id' => 'tasks',
        ],
        'task_types' => [
            'support_topics.task_type_id' => 'support_topics',
            'tasks.task_type_id' => 'tasks',
            'time_entries.task_type_id' => 'time_entries',
        ],
        'units_of_measure' => [
            'products.unit_of_measure_id' => 'products',
            'purchase_order_lines.unit_of_measure_id' => 'purchase_order_lines',
            'purchase_request_lines.unit_of_measure_id' => 'purchase_request_lines',
            'quote_lines.unit_of_measure_id' => 'quote_lines',
            'work_order_costs.unit_of_measure_id' => 'work_order_costs',
        ],
        'vat_rates' => [
            'agent_fiscal_profiles.vat_rate_id' => 'agent_fiscal_profiles',
            'invoice_lines.vat_rate_id' => 'invoice_lines',
            'products.vat_rate_id' => 'products',
            'purchase_invoice_lines.vat_rate_id' => 'purchase_invoice_lines',
            'purchase_order_lines.vat_rate_id' => 'purchase_order_lines',
            'purchase_request_lines.vat_rate_id' => 'purchase_request_lines',
            'quote_lines.vat_rate_id' => 'quote_lines',
            'work_order_costs.vat_rate_id' => 'work_order_costs',
        ],
        'work_order_payment_statuses' => [
            'work_order_line_payments.work_order_payment_status_id' => 'work_order_line_payments',
        ],
        'attributes' => [
            'attribute_category.attribute_id' => 'product_categories',
        ],
        'custom_field_definitions' => [],
    ];

    public const array OWNED = [
        'business_functions' => [
            'business_function_company.business_function_id',
            'business_function_operational_site.business_function_id',
        ],
        'company_closures' => [
            'company_closure_operational_site.company_closure_id',
            'absence_requests.company_closure_id',
        ],
        'job_roles' => [
            'job_activity_job_role.job_role_id',
            'job_role_skill.job_role_id',
            'job_role_staff_course.job_role_id',
        ],
        'product_categories' => [
            'attribute_category.category_id',
            'attribute_layouts.product_category_id',
        ],
        'roles' => [
            'role_field_permissions.role_id',
            'role_has_permissions.role_id',
        ],
        'support_topics' => [
            'support_topic_assignee.support_topic_id',
            'support_topic_watcher.support_topic_id',
        ],
        'attributes' => [
            'attribute_options.attribute_id',
        ],
        'custom_field_definitions' => [
            'custom_field_options.definition_id',
        ],
    ];
}
