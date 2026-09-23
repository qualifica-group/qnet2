<?php

use App\Stats\BusinessFunctions\BusinessFunctionsStatsDefinition;
use App\Stats\Campaigns\CampaignsStatsDefinition;
use App\Stats\Companies\CompaniesStatsDefinition;
use App\Stats\CompanySites\CompanySitesStatsDefinition;
use App\Stats\LeadImports\LeadImportsStatsDefinition;
use App\Stats\Leads\LeadsStatsDefinition;
use App\Stats\OperationalSites\OperationalSitesStatsDefinition;
use App\Stats\Opportunities\OpportunitiesStatsDefinition;
use App\Stats\ProductCategories\ProductCategoriesStatsDefinition;
use App\Stats\Products\ProductsStatsDefinition;
use App\Stats\Projects\ProjectsStatsDefinition;
use App\Stats\Quotes\QuotesStatsDefinition;
use App\Stats\Referents\ReferentsStatsDefinition;
use App\Stats\Registries\RegistriesStatsDefinition;
use App\Stats\Tasks\TasksStatsDefinition;
use App\Stats\Users\UsersStatsDefinition;

return [

    /*
    |--------------------------------------------------------------------------
    | Generic Domain-driven Module Statistics Registry (spec 0026)
    |--------------------------------------------------------------------------
    |
    | Maps each `{domain}` to its StatsDefinition class. ONE endpoint
    | (GET /api/stats/{domain}) serves every module; StatsRegistry resolves the
    | class below through the container (so its dependencies are injected). An
    | unregistered {domain} resolves to nothing and yields a 404 — this map is
    | the allow-list for the user-controlled {domain} segment.
    |
    | Adding a module's panel:
    |   1. class XxxStatsDefinition extends AbstractStatsDefinition
    |   2. add 'xxx' => XxxStatsDefinition::class here
    | No new controller, request, resource, route or frontend code is needed.
    |
    | The domain keys are the SAME as config/tables.php, so a module's table and
    | its statistics panel are always addressed by one identifier.
    |
    | `projects` joined the panel with one definition + the one line below and
    | no change to the generic code (OCP). Its dedicated
    | GET /api/projects/summary (spec 0023) keeps working unchanged.
    */

    'definitions' => [
        'registries' => RegistriesStatsDefinition::class,
        'referents' => ReferentsStatsDefinition::class,
        'companies' => CompaniesStatsDefinition::class,
        'operational-sites' => OperationalSitesStatsDefinition::class,
        'company-sites' => CompanySitesStatsDefinition::class,
        'products' => ProductsStatsDefinition::class,
        'product-categories' => ProductCategoriesStatsDefinition::class,
        'projects' => ProjectsStatsDefinition::class,
        'campaigns' => CampaignsStatsDefinition::class,
        'leads' => LeadsStatsDefinition::class,
        'business-functions' => BusinessFunctionsStatsDefinition::class,
        'users' => UsersStatsDefinition::class,
        'import-runs' => LeadImportsStatsDefinition::class,
        'opportunities' => OpportunitiesStatsDefinition::class,
        // Spec 0147: the work-order Task board's KPIs, scoped to the actor's
        // visible root tasks.
        'tasks' => TasksStatsDefinition::class,
        // Spec 0151 (dashboard, D-8): global quote KPIs, same visibility as
        // QuotesTableDefinition (unrestricted).
        'quotes' => QuotesStatsDefinition::class,
    ],

];
