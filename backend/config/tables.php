<?php

use App\Tables\AttributesTableDefinition;
use App\Tables\BusinessFunctionsTableDefinition;
use App\Tables\CampaignsTableDefinition;
use App\Tables\CommissionConfigurationsTableDefinition;
use App\Tables\CompaniesTableDefinition;
use App\Tables\CompanySitesTableDefinition;
use App\Tables\CustomFieldsTableDefinition;
use App\Tables\LeadImportsTableDefinition;
use App\Tables\LeadsTableDefinition;
use App\Tables\OperationalSitesTableDefinition;
use App\Tables\OpportunitiesTableDefinition;
use App\Tables\OpportunityStatusesTableDefinition;
use App\Tables\OpportunityWorkflowsTableDefinition;
use App\Tables\PaymentMethodsTableDefinition;
use App\Tables\PipelineStatusesTableDefinition;
use App\Tables\ProductCategoriesTableDefinition;
use App\Tables\ProductsTableDefinition;
use App\Tables\ProjectsTableDefinition;
use App\Tables\QuotesTableDefinition;
use App\Tables\QuoteStatusesTableDefinition;
use App\Tables\ReferentsTableDefinition;
use App\Tables\ReferentTypesTableDefinition;
use App\Tables\RegistriesTableDefinition;
use App\Tables\RequestManagementTableDefinition;
use App\Tables\RewardedReferentsTableDefinition;
use App\Tables\RewardStatusesTableDefinition;
use App\Tables\RewardTypesTableDefinition;
use App\Tables\RolesTableDefinition;
use App\Tables\SectorsTableDefinition;
use App\Tables\SourcesTableDefinition;
use App\Tables\TagsTableDefinition;
use App\Tables\UsersTableDefinition;
use App\Tables\VatRatesTableDefinition;

return [

    /*
    |--------------------------------------------------------------------------
    | Generic Domain-driven Table Registry
    |--------------------------------------------------------------------------
    |
    | Maps each table `{domain}` to its TableDefinition class. One pair of
    | endpoints (GET /api/tables/{domain}/columns, POST /api/tables/{domain}/rows)
    | serves every domain; TableRegistry resolves the class below through the
    | container (so its dependencies are injected). An unregistered {domain}
    | resolves to nothing and yields a 404.
    |
    | Adding a domain (e.g. products):
    |   1. class ProductsTableDefinition extends AbstractTableDefinition
    |   2. add 'products' => ProductsTableDefinition::class here
    | No new controller, service, request, resource or route is needed.
    |
    | See docs/adr/0002-generic-domain-driven-table-registry.md
    |     docs/api/0002-generic-tables.md
    */

    'definitions' => [
        'users' => UsersTableDefinition::class,
        'roles' => RolesTableDefinition::class,
        'business-functions' => BusinessFunctionsTableDefinition::class,
        'companies' => CompaniesTableDefinition::class,
        'commission-configurations' => CommissionConfigurationsTableDefinition::class,
        'company-sites' => CompanySitesTableDefinition::class,
        'operational-sites' => OperationalSitesTableDefinition::class,
        'referent-types' => ReferentTypesTableDefinition::class,
        'referents' => ReferentsTableDefinition::class,
        'registries' => RegistriesTableDefinition::class,
        'sectors' => SectorsTableDefinition::class,
        'attributes' => AttributesTableDefinition::class,
        'custom-fields' => CustomFieldsTableDefinition::class,
        'product-categories' => ProductCategoriesTableDefinition::class,
        'products' => ProductsTableDefinition::class,
        'sources' => SourcesTableDefinition::class,
        'tags' => TagsTableDefinition::class,
        'pipeline-statuses' => PipelineStatusesTableDefinition::class,
        'projects' => ProjectsTableDefinition::class,
        'campaigns' => CampaignsTableDefinition::class,
        'leads' => LeadsTableDefinition::class,
        'import-runs' => LeadImportsTableDefinition::class,
        'opportunities' => OpportunitiesTableDefinition::class,
        'opportunity-statuses' => OpportunityStatusesTableDefinition::class,
        'opportunity-workflows' => OpportunityWorkflowsTableDefinition::class,
        'payment-methods' => PaymentMethodsTableDefinition::class,
        'quote-statuses' => QuoteStatusesTableDefinition::class,
        'quotes' => QuotesTableDefinition::class,
        'request-management' => RequestManagementTableDefinition::class,
        'reward-types' => RewardTypesTableDefinition::class,
        'reward-statuses' => RewardStatusesTableDefinition::class,
        'rewarded-referents' => RewardedReferentsTableDefinition::class,
        'vat-rates' => VatRatesTableDefinition::class,
    ],

];
