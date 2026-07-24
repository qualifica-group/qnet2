<?php

use App\Authorization\AttributesAuthorization;
use App\Authorization\BusinessFunctionsAuthorization;
use App\Authorization\CampaignsAuthorization;
use App\Authorization\CompaniesAuthorization;
use App\Authorization\CompanySitesAuthorization;
use App\Authorization\CustomFieldsAuthorization;
use App\Authorization\LeadsAuthorization;
use App\Authorization\OperationalSitesAuthorization;
use App\Authorization\OpportunitiesAuthorization;
use App\Authorization\OpportunityStatusesAuthorization;
use App\Authorization\OpportunityWorkflowsAuthorization;
use App\Authorization\PipelineStatusesAuthorization;
use App\Authorization\ProductCategoriesAuthorization;
use App\Authorization\ProductsAuthorization;
use App\Authorization\ProjectsAuthorization;
use App\Authorization\ReferentsAuthorization;
use App\Authorization\ReferentTypesAuthorization;
use App\Authorization\RegistriesAuthorization;
use App\Authorization\RequestManagementAuthorization;
use App\Authorization\RewardedReferentsAuthorization;
use App\Authorization\RewardTypesAuthorization;
use App\Authorization\RolesAuthorization;
use App\Authorization\SectorsAuthorization;
use App\Authorization\SourcesAuthorization;
use App\Authorization\TagsAuthorization;
use App\Authorization\UsersAuthorization;
use App\Authorization\VatRatesAuthorization;

return [

    /*
    |--------------------------------------------------------------------------
    | Centralized Authorization Metadata Registry
    |--------------------------------------------------------------------------
    |
    | Maps each resource key to its ResourceAuthorization class. Resolved
    | through the container by App\Authorization\AuthorizationRegistry (so its
    | dependencies are injected), the same pattern as config/tables.php.
    |
    | GET /api/meta/{resource} and the users/roles CRUD endpoints all read
    | from this map. Adding a resource:
    |   1. class ProductsAuthorization extends AbstractResourceAuthorization
    |   2. add 'products' => ProductsAuthorization::class here
    | No new controller or route is needed.
    |
    | See docs/specs/0004-centralized-authorization-metadata.md
    |     docs/conventions/metadata-driven-forms.md
    */

    'definitions' => [
        'users' => UsersAuthorization::class,
        'roles' => RolesAuthorization::class,
        'business-functions' => BusinessFunctionsAuthorization::class,
        'companies' => CompaniesAuthorization::class,
        'company-sites' => CompanySitesAuthorization::class,
        'operational-sites' => OperationalSitesAuthorization::class,
        'referent-types' => ReferentTypesAuthorization::class,
        'referents' => ReferentsAuthorization::class,
        'registries' => RegistriesAuthorization::class,
        'sectors' => SectorsAuthorization::class,
        'attributes' => AttributesAuthorization::class,
        'custom-fields' => CustomFieldsAuthorization::class,
        'product-categories' => ProductCategoriesAuthorization::class,
        'products' => ProductsAuthorization::class,
        'sources' => SourcesAuthorization::class,
        'tags' => TagsAuthorization::class,
        'pipeline-statuses' => PipelineStatusesAuthorization::class,
        'projects' => ProjectsAuthorization::class,
        'campaigns' => CampaignsAuthorization::class,
        'leads' => LeadsAuthorization::class,
        'opportunities' => OpportunitiesAuthorization::class,
        'opportunity-statuses' => OpportunityStatusesAuthorization::class,
        'opportunity-workflows' => OpportunityWorkflowsAuthorization::class,
        'request-management' => RequestManagementAuthorization::class,
        'reward-types' => RewardTypesAuthorization::class,
        'rewarded-referents' => RewardedReferentsAuthorization::class,
        'vat-rates' => VatRatesAuthorization::class,
    ],

];
