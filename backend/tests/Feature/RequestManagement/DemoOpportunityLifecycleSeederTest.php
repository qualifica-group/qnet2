<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\Registry;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleAssignmentGuard;
use Database\Seeders\DemoCatalog\DemoCategoryCatalogue;
use Database\Seeders\DemoCategoryWorkflowSeeder;
use Database\Seeders\DemoOpportunityLifecycleSeeder;
use Database\Seeders\DemoOpportunitySeeder;
use Database\Seeders\DemoProductCategorySeeder;
use Database\Seeders\DemoProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

// The demo lifecycle pass: fills the opportunity-context attributes (spec
// 0061) and plans callbacks through the real RequestManagementService write
// path. Spec 0083 (D-2) removed the "stato di lavorazione" from the
// Opportunity — the seeder no longer walks a working-status set nor creates
// a requires_note note on this channel (see the seeder's own docblock); that
// coverage moved to the Offerta (tests/Feature/Quotes/QuoteRequiresNoteTest.php).
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
    test()->seed(DemoCategoryWorkflowSeeder::class);
    test()->seed(DemoOpportunitySeeder::class);
    test()->seed(DemoOpportunityLifecycleSeeder::class);
}

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

    $before = Opportunity::query()->pluck('attribute_values', 'id');

    test()->seed(DemoOpportunityLifecycleSeeder::class);

    expect(Opportunity::query()->pluck('attribute_values', 'id')->all())->toBe($before->all());
});
