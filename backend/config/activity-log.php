<?php

use App\Models\Attribute;
use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\CustomFieldDefinition;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\OpportunityStatus;
use App\Models\OpportunityWorkflow;
use App\Models\OpportunityWorkflowStatus;
use App\Models\PipelineStatus;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\Referent;
use App\Models\ReferentType;
use App\Models\Registry;
use App\Models\RewardType;
use App\Models\Role;
use App\Models\Sector;
use App\Models\Source;
use App\Models\Tag;
use App\Models\User;
use App\Models\VatRate;
use App\RequestManagement\RequestManagementActivityAuthorizer;

return [

    /*
    |--------------------------------------------------------------------------
    | Aggregated Activity Log — per-resource registry (spec 0034)
    |--------------------------------------------------------------------------
    |
    | Maps each `{resource}` (GET /api/activity-log/{resource}/{id}) to its
    | root model and the dot-path relations whose OWN activity_log entries are
    | aggregated alongside the root's (provenance kept via subject_type/
    | subject_id — see ActivityLogRegistry/AggregatedActivityService, both
    | fully generic). Adding a resource = one entry here, no controller/service
    | change.
    |
    | `personal_data`-aggregating resources (users, company-sites, referents,
    | registries — every HasPersonalData owner) share the same three
    | relations, mirroring `users`. `custom-fields` aggregates its own
    | `options` (CustomFieldOption, its only LogsModelActivity child). Every
    | other resource below has no aggregated child and declares no root model
    | with no own activity-logged relation, so `relations` is omitted
    | (ActivityLogRegistry defaults it to `[]`). `import-runs` is deliberately
    | excluded (spec 0034): it carries no activitylog.
    |
    */
    'resources' => [
        'attributes' => [
            'model' => Attribute::class,
        ],
        'business-functions' => [
            'model' => BusinessFunction::class,
        ],
        'campaigns' => [
            'model' => Campaign::class,
        ],
        'companies' => [
            'model' => Company::class,
        ],
        'company-sites' => [
            'model' => CompanySite::class,
            'relations' => ['personalData', 'personalData.contacts', 'personalData.addresses'],
        ],
        'custom-fields' => [
            'model' => CustomFieldDefinition::class,
            'relations' => ['options'],
        ],
        'leads' => [
            'model' => Lead::class,
        ],
        'operational-sites' => [
            'model' => OperationalSite::class,
        ],
        'opportunities' => [
            'model' => Opportunity::class,
        ],
        'opportunity-statuses' => [
            'model' => OpportunityStatus::class,
        ],
        'opportunity-workflows' => [
            'model' => OpportunityWorkflow::class,
        ],
        'opportunity-workflow-statuses' => [
            'model' => OpportunityWorkflowStatus::class,
        ],
        'pipeline-statuses' => [
            'model' => PipelineStatus::class,
        ],
        'product-categories' => [
            'model' => ProductCategory::class,
        ],
        'products' => [
            'model' => Product::class,
        ],
        'projects' => [
            'model' => Project::class,
        ],
        'referent-types' => [
            'model' => ReferentType::class,
        ],
        'request-management' => [
            // OPERATIVE view over Opportunity (spec 0049 D-1): the root model
            // is shared with `opportunities`, the authorization is NOT — hence
            // the dedicated authorizer (`request-management.viewActivity` + the
            // work panel's GA2 scope), the only resource here that overrides
            // the default Policy gate.
            'model' => Opportunity::class,
            'authorizer' => RequestManagementActivityAuthorizer::class,
            // Everything the operator can change from the work panel, in one
            // timeline: the request itself (workflow status, dynamic values,
            // next callback — explicit entries written by
            // RequestManagementService), the collaborative notes (soft-deleted
            // ones INCLUDED, so a removed note still leaves its trace), the
            // uploaded documents, and the client anagraphic block edited
            // inline in the panel (Registry's PersonalData card + contacts +
            // address).
            'relations' => [
                'notesWithTrashed',
                'attachments',
                'registry.personalData',
                'registry.personalData.contacts',
                'registry.personalData.addresses',
            ],
        ],
        'referents' => [
            'model' => Referent::class,
            'relations' => ['personalData', 'personalData.contacts', 'personalData.addresses'],
        ],
        'registries' => [
            'model' => Registry::class,
            'relations' => ['personalData', 'personalData.contacts', 'personalData.addresses'],
        ],
        'reward-types' => [
            'model' => RewardType::class,
        ],
        'roles' => [
            'model' => Role::class,
        ],
        'sectors' => [
            'model' => Sector::class,
        ],
        'sources' => [
            'model' => Source::class,
        ],
        'tags' => [
            'model' => Tag::class,
        ],
        'users' => [
            'model' => User::class,
            'relations' => ['personalData', 'personalData.contacts', 'personalData.addresses'],
        ],
        'vat-rates' => [
            'model' => VatRate::class,
        ],
    ],

];
