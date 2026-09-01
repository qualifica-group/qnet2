<?php

namespace App\Providers;

use App\Authorization\FieldPermissionRepository;
use App\CustomFields\CustomFieldEntityRegistry;
use App\CustomFields\CustomFieldProvider;
use App\CustomFields\CustomFieldRequestBag;
use App\FieldChangeRequests\ProtectedFieldRegistry;
use App\Mail\StagingMailRedirector;
use App\Models\Address;
use App\Models\Attachment;
use App\Models\Attribute;
use App\Models\AttributeLayout;
use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\CommissionConfiguration;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldOption;
use App\Models\DocumentLayout;
use App\Models\EmploymentProfile;
use App\Models\FieldChangeRequest;
use App\Models\Lead;
use App\Models\Note;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\PaymentMethod;
use App\Models\PersonalData;
use App\Models\PipelineStatus;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\Quote;
use App\Models\QuoteLineCommission;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowStatus;
use App\Models\Referent;
use App\Models\ReferentType;
use App\Models\Registry;
use App\Models\Reward;
use App\Models\RewardStatus;
use App\Models\RewardType;
use App\Models\Role;
use App\Models\Sector;
use App\Models\Source;
use App\Models\Tag;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\UserTablePreference;
use App\Models\VatRate;
use App\Services\Opportunities\OpportunityStatusResolver;
use App\Support\QuoteWorkflows\CategoryBranchResolver;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Singleton so FieldPermissionRepository::forRoleIds() memoizes its
        // one query across the WHOLE request (spec 0006), even though
        // AuthorizationRegistry::resolve() may build several
        // ResourceAuthorization instances per request (e.g. the FormRequest's
        // EnforcesFieldPermissions AND the controller's permissions block).
        $this->app->singleton(FieldPermissionRepository::class);

        // Scoped so OpportunityStatusResolver::defaultEntry() — the GLOBAL
        // default workflow's `open` row, the D-8 fallback every quote-less
        // Opportunity resolves to — is queried at most once per request.
        // Its memoization is per-INSTANCE, and four resources build the
        // computed status with a fresh `app()` call PER ROW (RewardResource,
        // RequestManagementResource, OpportunityResource, RecordDetails): a
        // new instance per row meant a new query per row, so a page of N
        // quote-less rows cost N queries instead of one. Scoped, not
        // singleton: the cached row must not outlive the request (a queue
        // worker would otherwise serve a stale name/color forever).
        $this->app->scoped(OpportunityStatusResolver::class);

        // Singleton so the custom-fieldable entity map (spec 0021) — built by
        // intersecting config/tables.php with config/authorization.php and
        // resolving a TableDefinition per match — is computed at most once
        // per request instead of once per call site (meta, table decorator,
        // write pipeline all consult it).
        $this->app->singleton(CustomFieldEntityRegistry::class);

        // Scoped so the per-request memo of active custom field definitions
        // (spec 0021) is shared by every decorator in ONE request (table, meta,
        // write all resolve it) yet reset between requests.
        $this->app->scoped(CustomFieldProvider::class);

        // Scoped so the write pipeline's CustomFieldRequestBag (spec 0021 —
        // INNESTO WRITE) is shared by every consumer within ONE request
        // (CaptureCustomFields middleware writes it, HasCustomFields'
        // saving/saved observers read it) but reset between requests under
        // Octane/long-running workers.
        $this->app->scoped(CustomFieldRequestBag::class);

        // Scoped so the product-category tree projection behind the
        // quote-workflow branch criterion (spec 0092) is read ONCE per
        // request: QuoteCriterionFieldRegistry (which values a quote matches)
        // and QuoteWorkflowResolver (how far up it matched) both depend on it,
        // and a fresh instance per injection would mean a second query plus a
        // second per-quote memo. Scoped, not singleton: a reparented category
        // must not be resolved against a stale tree forever on a worker.
        $this->app->scoped(CategoryBranchResolver::class);

        // Singleton, same reasoning as FieldPermissionRepository above: the
        // protected-fields config (spec 0078) is parsed once per request even
        // though it is consulted on every field-permission resolution
        // (ProtectedFieldAwareAuthorization) AND by permissions:sync.
        $this->app->singleton(ProtectedFieldRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Stable morph aliases: the *_type columns (polymorphic relations plus
        // activity-log subject/causer) store these short strings instead of
        // fully-qualified class names. Defence in depth against morph-type
        // injection — enforceMorphMap makes any unmapped model throw rather than
        // silently persisting an arbitrary FQCN — and immunity to class
        // renames/moves. The 'user' alias matches the public attachable alias
        // already used by config/attachments.php.
        //
        // Because enforcement is strict, EVERY model that can appear in a morph
        // column (here: any audited subject/causer and every polymorphic owner)
        // must be listed.
        Relation::enforceMorphMap([
            'user' => User::class,
            'role' => Role::class,
            'personal_data' => PersonalData::class,
            'contact' => Contact::class,
            'address' => Address::class,
            'attachment' => Attachment::class,
            'user_table_preference' => UserTablePreference::class,
            'business_function' => BusinessFunction::class,
            'company' => Company::class,
            'company_site' => CompanySite::class,
            'operational_site' => OperationalSite::class,
            'employment_profile' => EmploymentProfile::class,
            'referent' => Referent::class,
            'referent_type' => ReferentType::class,
            'attribute' => Attribute::class,
            'attribute_layout' => AttributeLayout::class,
            'product_category' => ProductCategory::class,
            'product' => Product::class,
            'unit_of_measure' => UnitOfMeasure::class,
            'source' => Source::class,
            'sector' => Sector::class,
            'tag' => Tag::class,
            'registry' => Registry::class,
            'custom_field' => CustomFieldDefinition::class,
            'custom_field_option' => CustomFieldOption::class,
            'pipeline_status' => PipelineStatus::class,
            'project' => Project::class,
            'campaign' => Campaign::class,
            'lead' => Lead::class,
            'note' => Note::class,
            'opportunity' => Opportunity::class,
            'reward' => Reward::class,
            'reward_type' => RewardType::class,
            'reward_status' => RewardStatus::class,
            'vat_rate' => VatRate::class,
            'quote' => Quote::class,
            'quote_workflow' => QuoteWorkflow::class,
            'quote_workflow_status' => QuoteWorkflowStatus::class,
            'commission_configuration' => CommissionConfiguration::class,
            'quote_line_commission' => QuoteLineCommission::class,
            'payment_method' => PaymentMethod::class,
            // Spec 0069 (document-layouts module, wave 1 backend): DocumentLayout
            // uses LogsModelActivity, whose bootLogsActivity() unconditionally
            // calls Activity::subject()->associate($model) on every create/update
            // — i.e. $model->getMorphClass() — BEFORE checking whether logging is
            // even enabled. With Relation::enforceMorphMap() active (strict mode,
            // see the comment above), an unregistered model throws
            // ClassMorphViolationException on its very first save, not just when
            // its images() morphMany (also declared on the model) is used. This
            // one-line registration was verified against
            // vendor/spatie/laravel-activitylog and vendor/laravel/framework
            // (Model::getMorphClass()/MorphOneOrMany::__construct) before adding
            // it — it is not a guess. No other file in this list was touched.
            'document_layout' => DocumentLayout::class,
            // Spec 0072 (contracts module): Contract/ContractStatus both use
            // LogsModelActivity — same reasoning as document_layout above.
            // Contract is also a HasAttachments owner, so this 'contract'
            // alias must match config('attachments.attachable_types').
            'contract' => Contract::class,
            'contract_status' => ContractStatus::class,
            // Spec 0078 (field-change-requests module): FieldChangeRequest
            // uses LogsModelActivity, same reasoning as document_layout above.
            'field_change_request' => FieldChangeRequest::class,
        ]);

        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->hasRole('super-admin')) {
                return true;
            }

            return null;
        });

        // Staging safety net: with MAIL_ALWAYS_TO set, every email is funnelled
        // to that one mailbox instead of the real contacts. No-op when unset.
        $this->app->make(StagingMailRedirector::class)->handle();
    }
}
