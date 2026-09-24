<?php

use App\Models\BusinessFunction;
use App\Models\Contract;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Task;
use App\Models\TaskType;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Console\Command\Command as ConsoleCommand;

// The terminal front-end over QualificaSampleDataSeeder (user directive
// 2026-09-08): the batch sizes as flags, so a bigger dataset is one run rather
// than several. What is pinned here is the wiring — the flags reaching the
// seeders' own run() parameters — not the chain itself, which
// QualificaSampleDataSeederTest already covers.
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()]);
    Product::factory()->create(['category_id' => $category->getKey()]);
    OperationalSite::factory()->create();
    User::factory()->count(3)->create();
    TaskType::factory()->create();
});

it('sizes every step from its own flag', function (): void {
    test()->artisan('qualifica:seed-sample', [
        '--leads' => 9,
        '--converted-leads' => 2,
        '--opportunities' => 3,
        '--requests' => 2,
        '--quotes' => 3,
        '--contracts' => 2,
        '--work-orders' => 1,
        '--tasks' => 4,
        '--time-entries' => 5,
    ])->assertSuccessful();

    expect(Lead::query()->count())->toBe(9)
        ->and(Lead::query()->has('opportunity')->count())->toBe(2)
        // 2 converted + 3 lead-less + 2 born with a request.
        ->and(Opportunity::query()->count())->toBe(7)
        // 2 born with a request + 3 on the deals that had none.
        ->and(Quote::query()->count())->toBe(5)
        ->and(Contract::query()->count())->toBe(2)
        ->and(WorkOrder::query()->count())->toBe(1)
        ->and(Task::query()->count())->toBe(4)
        // 5 + the one the single completed Task logs.
        ->and(TimeEntry::query()->count())->toBe(6);
});

it('falls back to the seeders own defaults when no flag is given', function (): void {
    test()->artisan('qualifica:seed-sample')->assertSuccessful();

    expect(Lead::query()->count())->toBe(40)
        ->and(Opportunity::query()->count())->toBe(30)
        ->and(Quote::query()->count())->toBe(23)
        ->and(Contract::query()->count())->toBe(8)
        ->and(WorkOrder::query()->count())->toBe(4)
        ->and(Task::query()->count())->toBe(30)
        ->and(TimeEntry::query()->count())->toBe(70);
});

it('sizes every domain at once from --size, a per-domain flag still winning', function (): void {
    // User directive 2026-09-24: one number for the whole batch. Leads are
    // scaled x3 so the 4 opportunities and 4 requests find free Anagrafiche
    // next to the 4 converted leads.
    test()->artisan('qualifica:seed-sample', ['--size' => 4, '--time-entries' => 1])->assertSuccessful();

    expect(Lead::query()->count())->toBe(12)
        ->and(Lead::query()->has('opportunity')->count())->toBe(4)
        // 4 converted + 4 lead-less + 4 born with a request.
        ->and(Opportunity::query()->count())->toBe(12)
        // 4 born with a request + 4 on the deals that had none.
        ->and(Quote::query()->count())->toBe(8)
        ->and(Task::query()->count())->toBe(4)
        // 1 (the flag wins over --size) + the one the single completed Task logs.
        ->and(TimeEntry::query()->count())->toBe(2);
});

it('refuses a flag that is not a positive integer instead of seeding an empty batch', function (): void {
    // Casting blindly would turn this into 0 and report success — a silent
    // failure, not a default.
    test()->artisan('qualifica:seed-sample', ['--leads' => 'abc'])
        ->assertExitCode(ConsoleCommand::INVALID);

    expect(Lead::query()->count())->toBe(0);
});

it('appends on a second run, like the seeder it drives', function (): void {
    $flags = ['--leads' => 6, '--opportunities' => 2, '--requests' => 1, '--quotes' => 1, '--contracts' => 1, '--work-orders' => 1, '--tasks' => 2, '--time-entries' => 2];

    test()->artisan('qualifica:seed-sample', $flags)->assertSuccessful();
    test()->artisan('qualifica:seed-sample', $flags)->assertSuccessful();

    expect(Lead::query()->count())->toBe(12)
        ->and(Quote::query()->count())->toBe(4)
        ->and(Contract::query()->count())->toBe(2)
        ->and(WorkOrder::query()->count())->toBe(2)
        ->and(Task::query()->count())->toBe(4)
        ->and(TimeEntry::query()->count())->toBe(6);
});
