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

// The demo lifecycle pass: plans callbacks through the real
// RequestManagementService write path. Spec 0083 (D-2) removed the "stato di
// lavorazione" from the Opportunity — the seeder no longer walks a
// working-status set nor creates a requires_note note on this channel (see
// the seeder's own docblock); that coverage moved to the Offerta
// (tests/Feature/Quotes/QuoteRequiresNoteTest.php). Spec 0084 (D-1) removed
// the opportunity-context attribute fill this seeder used to perform too —
// see tests/Feature/Quotes/DemoQuoteSeederTest.php for its Offerta-side
// replacement (AC-050).
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

it('plans a callback on some of the seeded requests', function (): void {
    seedDemoLifecycle();

    $withCallback = Opportunity::query()->whereNotNull('next_callback_at')->count();

    expect($withCallback)->toBeGreaterThan(0);
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

    $before = Opportunity::query()->pluck('next_callback_at', 'id');

    test()->seed(DemoOpportunityLifecycleSeeder::class);

    expect(Opportunity::query()->pluck('next_callback_at', 'id')->all())->toEqual($before->all());
});
