<?php

use App\Models\BusinessFunction;
use App\Models\OpportunityWorkflow;
use App\Models\OpportunityWorkflowStatus;
use App\Models\ProductCategory;
use Database\Seeders\DemoCatalog\DemoCategoryCatalogue;
use Database\Seeders\DemoCatalog\DemoWorkflowStatusCatalogue;
use Database\Seeders\DemoCategoryWorkflowSeeder;
use Database\Seeders\DemoProductCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The demo "stati di lavorazione" (spec 0047): one workflow per demo category,
// matched on that category, carrying its branch's pick list.
uses(RefreshDatabase::class);

function seedDemoWorkflows(): void
{
    foreach (DemoCategoryCatalogue::TREE as $branch) {
        BusinessFunction::query()->firstOrCreate(['name' => $branch['business_function']]);
    }

    test()->seed(DemoProductCategorySeeder::class);
    test()->seed(DemoCategoryWorkflowSeeder::class);
}

it('provisions one active workflow per demo category, idempotently', function (): void {
    seedDemoWorkflows();
    seedDemoWorkflows(); // re-run: natural key (name / signature), no duplicates.

    $categoryNames = DemoCategoryCatalogue::categoryNames();

    expect(OpportunityWorkflow::query()->count())->toBe(count($categoryNames));

    foreach ($categoryNames as $categoryName) {
        $workflow = OpportunityWorkflow::query()->where('name', $categoryName)->with('criteria')->first();
        $category = ProductCategory::query()->where('name', $categoryName)->firstOrFail();

        expect($workflow)->not->toBeNull($categoryName)
            ->and($workflow->is_active)->toBeTrue($categoryName)
            ->and($workflow->criteria)->toHaveCount(1, $categoryName)
            ->and($workflow->criteria->first()->field)->toBe('product_category_id', $categoryName)
            ->and($workflow->criteria->first()->value_id)->toBe($category->id, $categoryName);
    }
});

it('seeds the branch pick list between the four pinned system rows', function (): void {
    seedDemoWorkflows();

    $workflow = OpportunityWorkflow::query()->where('name', 'Corsi Online')->firstOrFail();
    $statuses = OpportunityWorkflowStatus::query()
        ->where('opportunity_workflow_id', $workflow->id)
        ->orderBy('sort_order')
        ->get();

    $custom = DemoWorkflowStatusCatalogue::CUSTOM['Servizi Formativi'];
    $pinned = DemoWorkflowStatusCatalogue::PINNED['Servizi Formativi'];

    expect($statuses)->toHaveCount(count($custom) + count($pinned))
        ->and($statuses->first()->system_key)->toBe('open')
        // The branch's own labels take over the writer's generic ones.
        ->and($statuses->first()->name)->toBe($pinned['open']['name'])
        ->and($statuses->slice(-3)->pluck('system_key')->all())->toBe(['validated', 'closed_won', 'closed_lost'])
        ->and($statuses->slice(1, count($custom))->pluck('name')->all())->toBe(array_column($custom, 'name'));
});

it('keeps the note requirement of the states that carry one', function (): void {
    seedDemoWorkflows();

    $workflow = OpportunityWorkflow::query()->where('name', 'Consulenza IT')->firstOrFail();
    $noteRequiring = OpportunityWorkflowStatus::query()
        ->where('opportunity_workflow_id', $workflow->id)
        ->where('requires_note', true)
        ->pluck('name')
        ->all();

    expect($noteRequiring)->toContain('Offerta inviata')
        ->toContain('Non interessato');
});

it('is a no-op when the demo tree is not seeded', function (): void {
    test()->seed(DemoCategoryWorkflowSeeder::class);

    expect(OpportunityWorkflow::query()->count())->toBe(0);
});
