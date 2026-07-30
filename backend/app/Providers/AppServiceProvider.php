<?php

namespace App\Providers;

use App\Authorization\FieldPermissionRepository;
use App\CustomFields\CustomFieldEntityRegistry;
use App\CustomFields\CustomFieldProvider;
use App\CustomFields\CustomFieldRequestBag;
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
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldOption;
use App\Models\EmploymentProfile;
use App\Models\Lead;
use App\Models\Note;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\OpportunityStatus;
use App\Models\OpportunityWorkflow;
use App\Models\OpportunityWorkflowStatus;
use App\Models\PaymentMethod;
use App\Models\PersonalData;
use App\Models\PipelineStatus;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\Quote;
use App\Models\QuoteLineCommission;
use App\Models\QuoteStatus;
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
use App\Models\User;
use App\Models\UserTablePreference;
use App\Models\VatRate;
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
            'opportunity_status' => OpportunityStatus::class,
            'opportunity_workflow' => OpportunityWorkflow::class,
            'opportunity_workflow_status' => OpportunityWorkflowStatus::class,
            'reward' => Reward::class,
            'reward_type' => RewardType::class,
            'reward_status' => RewardStatus::class,
            'vat_rate' => VatRate::class,
            'quote' => Quote::class,
            'quote_status' => QuoteStatus::class,
            'commission_configuration' => CommissionConfiguration::class,
            'quote_line_commission' => QuoteLineCommission::class,
            'payment_method' => PaymentMethod::class,
        ]);

        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->hasRole('super-admin')) {
                return true;
            }

            return null;
        });
    }
}
