<?php

declare(strict_types=1);

use App\Enums\ImportStatus;
use App\Jobs\ProcessStagedImportJob;
use App\Jobs\StageImportJob;
use App\Models\BusinessFunction;
use App\Models\ImportRun;
use App\Models\OperationalSite;
use App\Models\ProductCategory;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowCriterion;
use App\Models\QuoteWorkflowStatus;
use App\Models\Source;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

/*
|--------------------------------------------------------------------------
| Request-management import fixtures (spec 0348)
|--------------------------------------------------------------------------
|
| Synthetic data only: no real person from the user's files appears here.
| Shared by the files of tests/Feature/Imports/RequestManagement.
*/

if (! function_exists('requestImportActor')) {
    /**
     * An importer holding only `request-management.import`.
     */
    function requestImportActor(): User
    {
        Permission::findOrCreate('request-management.import');

        return User::factory()->create()->givePermissionTo('request-management.import');
    }
}

if (! function_exists('requestImportScenario')) {
    /**
     * A selectable product category with its OWN workflow (criterion on the
     * category): Aperta (open) / In trattativa (pending) / Attesa _ App. APL
     * (pending) / Chiusa positiva / Chiusa negativa.
     *
     * @return array{category: ProductCategory, workflow: QuoteWorkflow, statuses: array<string, QuoteWorkflowStatus>}
     */
    function requestImportScenario(): array
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
            'is_selectable' => true,
            'is_active' => true,
        ]);
        $workflow = QuoteWorkflow::factory()->create();
        QuoteWorkflowCriterion::factory()->create([
            'quote_workflow_id' => $workflow->id,
            'field' => 'product_category_id',
            'value_id' => $category->id,
        ]);

        $statuses = [
            'open' => QuoteWorkflowStatus::factory()->system('open')->create(['quote_workflow_id' => $workflow->id, 'name' => 'Aperta']),
            'negotiation' => QuoteWorkflowStatus::factory()->create(['quote_workflow_id' => $workflow->id, 'name' => 'In trattativa', 'group' => 'pending', 'sort_order' => 1]),
            'wait_apl' => QuoteWorkflowStatus::factory()->create(['quote_workflow_id' => $workflow->id, 'name' => 'Attesa _ App. APL', 'group' => 'pending', 'sort_order' => 2]),
            'won' => QuoteWorkflowStatus::factory()->system('closed_won')->create(['quote_workflow_id' => $workflow->id]),
            'lost' => QuoteWorkflowStatus::factory()->system('closed_lost')->create(['quote_workflow_id' => $workflow->id]),
        ];

        return ['category' => $category, 'workflow' => $workflow, 'statuses' => $statuses];
    }
}

if (! function_exists('requestImportRun')) {
    /**
     * Stages a CSV through the real StageImportJob (recognizers, validation,
     * duplicate match) for the `request-management` definition.
     *
     * @param  array<string, string>  $columnMapping  column key => target
     * @param  array<string, mixed>  $globalConfig
     * @param  array<string, mixed>|null  $valueMappings
     */
    function requestImportRun(User $actor, string $csv, array $columnMapping, array $globalConfig, ?array $valueMappings = null, string $dedup = 'create_new'): ImportRun
    {
        Storage::fake('local');
        Storage::disk('local')->put('imports/request-import.csv', $csv);

        $run = ImportRun::factory()->create([
            'resource' => 'request-management',
            'user_id' => $actor->id,
            'status' => ImportStatus::Staging,
            'stored_path' => 'imports/request-import.csv',
            'column_mapping' => $columnMapping,
            'global_config' => $globalConfig,
            'value_mappings' => $valueMappings,
            'dedup_strategy' => $dedup,
        ]);

        app()->call([new StageImportJob($run->id), 'handle']);

        return $run->fresh();
    }
}

if (! function_exists('requestImportCommit')) {
    function requestImportCommit(ImportRun $run): ImportRun
    {
        $run->update(['status' => ImportStatus::Processing]);

        app()->call([new ProcessStagedImportJob($run->id), 'handle']);

        return $run->fresh();
    }
}

if (! function_exists('requestImportGlobalConfig')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function requestImportGlobalConfig(ProductCategory $category, array $overrides = []): array
    {
        return [
            'product_category_id' => $category->id,
            'operational_site_id' => OperationalSite::factory()->create()->id,
            'source_id' => Source::factory()->create()->id,
            ...$overrides,
        ];
    }
}
