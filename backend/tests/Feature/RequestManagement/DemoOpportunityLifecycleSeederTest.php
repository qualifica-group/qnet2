<?php

use App\Models\BusinessFunction;
use App\Models\Note;
use App\Models\Opportunity;
use App\Models\OpportunityWorkflowStatus;
use App\Models\Registry;
use App\Models\Role;
use App\Models\User;
use App\Services\Opportunities\OpportunityWorkflowResolver;
use App\Services\RoleAssignmentGuard;
use Database\Seeders\DemoCatalog\DemoCategoryCatalogue;
use Database\Seeders\DemoCategoryWorkflowSeeder;
use Database\Seeders\DemoOpportunityLifecycleSeeder;
use Database\Seeders\DemoOpportunitySeeder;
use Database\Seeders\DemoOpportunityStatusSeeder;
use Database\Seeders\DemoProductCategorySeeder;
use Database\Seeders\DemoProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

// The demo lifecycle pass: walks the seeded requests through their working
// statuses (spec 0047) and fills the opportunity-context attributes (spec
// 0061) through the real RequestManagementService write path.
uses(RefreshDatabase::class);

function seedDemoLifecycle(): void
{
    Registry::factory()->count(3)->create();
    User::factory()->count(5)->create();

    $role = Role::findOrCreate(RoleAssignmentGuard::PRIVILEGED_ROLE);
    $role->givePermissionTo(Permission::findOrCreate('notes.create'));
    User::factory()->create()->assignRole($role);

    foreach (DemoCategoryCatalogue::TREE as $branch) {
        BusinessFunction::query()->firstOrCreate(['name' => $branch['business_function']]);
    }

    test()->seed(DemoProductCategorySeeder::class);
    test()->seed(DemoProductSeeder::class);
    test()->seed(DemoOpportunityStatusSeeder::class);
    test()->seed(DemoCategoryWorkflowSeeder::class);
    test()->seed(DemoOpportunitySeeder::class);
    test()->seed(DemoOpportunityLifecycleSeeder::class);
}

it('spreads the requests over their own workflow statuses, not only the open one', function (): void {
    seedDemoLifecycle();

    $opportunities = Opportunity::query()->with('workflowStatus')->get();

    expect($opportunities)->not->toBeEmpty();

    $reachedGroups = $opportunities
        ->pluck('workflowStatus')
        ->filter()
        ->pluck('group')
        ->map(static fn ($group): string => $group->value)
        ->unique();

    // The whole cycle is represented, not just the state creation assigns.
    expect($reachedGroups)->toContain('open')
        ->toContain('pending')
        ->toContain('closed_won')
        ->toContain('closed_lost');
});

it('keeps every advance inside the resolved workflow status set', function (): void {
    seedDemoLifecycle();

    foreach (Opportunity::query()->with('productLines')->get() as $opportunity) {
        $workflow = app(OpportunityWorkflowResolver::class)->resolve($opportunity);
        $allowedIds = OpportunityWorkflowStatus::query()
            ->where(fn ($query) => $workflow === null
                ? $query->whereNull('opportunity_workflow_id')
                : $query->where('opportunity_workflow_id', $workflow->id))
            ->pluck('id')
            ->all();

        expect($opportunity->opportunity_workflow_status_id)->toBeIn($allowedIds, $opportunity->name);
    }
});

it('fills the opportunity-context attribute values of the categories it works', function (): void {
    seedDemoLifecycle();

    $withValues = Opportunity::query()->whereNotNull('attribute_values')->get()
        ->filter(static fn (Opportunity $opportunity): bool => $opportunity->attribute_values !== []);

    expect($withValues)->not->toBeEmpty();

    $codes = $withValues->flatMap(static fn (Opportunity $opportunity): array => array_keys($opportunity->attribute_values))->unique();

    // Only codes the applicable set carries, never a product-context one.
    expect($codes)->toContain('demo_processing_notes')
        ->and($codes)->not->toContain('demo_course_hours');
});

it('creates the mandatory note whenever it advances to a requires_note status', function (): void {
    seedDemoLifecycle();

    $noteRequiringIds = OpportunityWorkflowStatus::query()->where('requires_note', true)->pluck('id')->all();
    $advanced = Opportunity::query()->whereIn('opportunity_workflow_status_id', $noteRequiringIds)->get();

    expect($advanced)->not->toBeEmpty();

    foreach ($advanced as $opportunity) {
        expect(Note::query()->where('notable_id', $opportunity->id)->exists())->toBeTrue($opportunity->name);
    }
});

it('is a no-op without an actor allowed to write notes', function (): void {
    Registry::factory()->count(2)->create();
    User::factory()->count(3)->create();

    foreach (DemoCategoryCatalogue::TREE as $branch) {
        BusinessFunction::query()->firstOrCreate(['name' => $branch['business_function']]);
    }

    test()->seed(DemoProductCategorySeeder::class);
    test()->seed(DemoProductSeeder::class);
    test()->seed(DemoCategoryWorkflowSeeder::class);
    test()->seed(DemoOpportunitySeeder::class);

    $before = Opportunity::query()->pluck('opportunity_workflow_status_id', 'id');

    test()->seed(DemoOpportunityLifecycleSeeder::class);

    expect(Opportunity::query()->pluck('opportunity_workflow_status_id', 'id')->all())->toBe($before->all());
});
