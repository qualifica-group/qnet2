<?php

use App\Authorization\AttributesAuthorization;
use App\Authorization\BusinessFunctionsAuthorization;
use App\Authorization\CampaignsAuthorization;
use App\Authorization\CommissionConfigurationsAuthorization;
use App\Authorization\CompaniesAuthorization;
use App\Authorization\CompanySitesAuthorization;
use App\Authorization\ContractsAuthorization;
use App\Authorization\ContractStatusesAuthorization;
use App\Authorization\CustomFieldsAuthorization;
use App\Authorization\DocumentLayoutsAuthorization;
use App\Authorization\LeadsAuthorization;
use App\Authorization\OperationalSitesAuthorization;
use App\Authorization\OpportunitiesAuthorization;
use App\Authorization\OpportunityWorkflowsAuthorization;
use App\Authorization\PaymentMethodsAuthorization;
use App\Authorization\PipelineStatusesAuthorization;
use App\Authorization\ProductCategoriesAuthorization;
use App\Authorization\ProductsAuthorization;
use App\Authorization\ProjectsAuthorization;
use App\Authorization\QuotesAuthorization;
use App\Authorization\QuoteStatusesAuthorization;
use App\Authorization\ReferentsAuthorization;
use App\Authorization\ReferentTypesAuthorization;
use App\Authorization\RegistriesAuthorization;
use App\Authorization\RequestManagementAuthorization;
use App\Authorization\RewardedReferentsAuthorization;
use App\Authorization\RewardStatusesAuthorization;
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
        'commission-configurations' => CommissionConfigurationsAuthorization::class,
        'company-sites' => CompanySitesAuthorization::class,
        // spec 0072: il modulo Contratti e il suo configuratore di stati.
        'contracts' => ContractsAuthorization::class,
        'contract-statuses' => ContractStatusesAuthorization::class,
        'operational-sites' => OperationalSitesAuthorization::class,
        'referent-types' => ReferentTypesAuthorization::class,
        'referents' => ReferentsAuthorization::class,
        'registries' => RegistriesAuthorization::class,
        'sectors' => SectorsAuthorization::class,
        'attributes' => AttributesAuthorization::class,
        'custom-fields' => CustomFieldsAuthorization::class,
        'document-layouts' => DocumentLayoutsAuthorization::class,
        'product-categories' => ProductCategoriesAuthorization::class,
        'products' => ProductsAuthorization::class,
        'sources' => SourcesAuthorization::class,
        'tags' => TagsAuthorization::class,
        'pipeline-statuses' => PipelineStatusesAuthorization::class,
        'projects' => ProjectsAuthorization::class,
        'campaigns' => CampaignsAuthorization::class,
        'leads' => LeadsAuthorization::class,
        'opportunities' => OpportunitiesAuthorization::class,
        'opportunity-workflows' => OpportunityWorkflowsAuthorization::class,
        'payment-methods' => PaymentMethodsAuthorization::class,
        'quote-statuses' => QuoteStatusesAuthorization::class,
        'quotes' => QuotesAuthorization::class,
        'request-management' => RequestManagementAuthorization::class,
        'reward-types' => RewardTypesAuthorization::class,
        'reward-statuses' => RewardStatusesAuthorization::class,
        'rewarded-referents' => RewardedReferentsAuthorization::class,
        'vat-rates' => VatRatesAuthorization::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission-only resources
    |--------------------------------------------------------------------------
    |
    | Resources that own a real permission but NO form of their own, so they
    | have no ResourceAuthorization above (no fields, no metadata endpoint) —
    | yet their permission must stay assignable from the Role form.
    |
    | `notes` (spec 0052 D-6) and `attachments` are the two cases this exists
    | for: both are agnostic cross-module components mounted by other modules,
    | so neither owns a form of its own, yet both gate their endpoints on real
    | permissions (`notes.create`; `attachments.viewAny/view/create/delete`).
    | Without this list those permissions exist in the catalogue while being
    | un-grantable from the UI — reachable only by a seeder or the super-admin
    | bypass. Note that the host module's own gate (e.g.
    | `request-management.viewDocuments`) only opens the surface: it does not
    | authorize the component's endpoints.
    |
    | This is NOT the place for the indirect sub-entity permissions
    | (addresses.*, contacts.*, personal_data.*): those are governed by the
    | field-permission matrix of their parent form and must stay out of the
    | Role form checkboxes.
    |
    | See App\Authorization\AssignablePermissionCatalogue
    */

    'permission_only_resources' => [
        'notes',
        'attachments',
    ],

];
