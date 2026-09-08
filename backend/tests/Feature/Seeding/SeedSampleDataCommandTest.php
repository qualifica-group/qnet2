<?php

use App\Models\BusinessFunction;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\User;
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
});

it('sizes every step from its own flag', function (): void {
    test()->artisan('qualifica:seed-sample', [
        '--leads' => 9,
        '--converted-leads' => 2,
        '--opportunities' => 3,
        '--requests' => 2,
    ])->assertSuccessful();

    expect(Lead::query()->count())->toBe(9)
        ->and(Lead::query()->has('opportunity')->count())->toBe(2)
        // 2 converted + 3 lead-less + 2 born with a request.
        ->and(Opportunity::query()->count())->toBe(7)
        ->and(Quote::query()->count())->toBe(2);
});

it('falls back to the seeders own defaults when no flag is given', function (): void {
    test()->artisan('qualifica:seed-sample')->assertSuccessful();

    expect(Lead::query()->count())->toBe(40)
        ->and(Opportunity::query()->count())->toBe(30)
        ->and(Quote::query()->count())->toBe(8);
});

it('refuses a flag that is not a positive integer instead of seeding an empty batch', function (): void {
    // Casting blindly would turn this into 0 and report success — a silent
    // failure, not a default.
    test()->artisan('qualifica:seed-sample', ['--leads' => 'abc'])
        ->assertExitCode(ConsoleCommand::INVALID);

    expect(Lead::query()->count())->toBe(0);
});

it('appends on a second run, like the seeder it drives', function (): void {
    test()->artisan('qualifica:seed-sample', ['--leads' => 6, '--opportunities' => 2, '--requests' => 1])
        ->assertSuccessful();
    test()->artisan('qualifica:seed-sample', ['--leads' => 6, '--opportunities' => 2, '--requests' => 1])
        ->assertSuccessful();

    expect(Lead::query()->count())->toBe(12)
        ->and(Quote::query()->count())->toBe(2);
});
