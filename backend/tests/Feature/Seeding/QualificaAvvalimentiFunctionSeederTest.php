<?php

use App\Enums\CategoryManagementMode;
use App\Models\BusinessFunction;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\Assignment\OperatorCompetence;
use Database\Seeders\QualificaAvvalimentiFunctionSeeder as Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/** User directive 2026-11-11: Avvalimenti set up like ISO, its legacy roots reachable from the new function's scope. */
uses(RefreshDatabase::class);

function avvalimentiLegacyRoot(BusinessFunction $legacyFunction, int $oldId): ProductCategory
{
    return ProductCategory::factory()->create([
        'name' => "[OLD] AVVALIMENTI {$oldId}",
        'old_id' => $oldId,
        'is_legacy' => true,
        'is_active' => false,
        'business_function_id' => $legacyFunction->id,
        'requires_quote' => true,
        'management_mode' => CategoryManagementMode::Multiple,
        'single_quote_per_opportunity' => true,
        'generates_contract' => true,
    ]);
}

it('moves the legacy roots under a new function and category and scopes the member on them, idempotently', function () {
    $legacyFunction = BusinessFunction::factory()->create(['name' => '[OLD] AVVALIMENTI', 'is_active' => false]);
    $gare = BusinessFunction::factory()->create(['name' => 'Gare']);
    $roots = collect(Seeder::LEGACY_CATEGORY_OLD_IDS)->map(fn (int $oldId) => avvalimentiLegacyRoot($legacyFunction, $oldId));
    $legacyChild = ProductCategory::factory()->childOf($roots->last())->create(['is_legacy' => true]);
    $unrelated = ProductCategory::factory()->create(['is_legacy' => true, 'old_id' => 999, 'business_function_id' => $legacyFunction->id]);
    $member = User::factory()->create(['email' => Seeder::MEMBER_EMAIL]);
    $employment = $member->employment()->create();
    $employment->productLines()->create(['business_function_id' => $gare->id, 'product_category_id' => null, 'share_percentage' => 100]);

    $this->seed(Seeder::class);
    $this->seed(Seeder::class);

    $function = BusinessFunction::query()->where('name', Seeder::FUNCTION_NAME)->sole();
    $category = ProductCategory::query()->where('name', Seeder::CATEGORY_NAME)->sole();

    expect($function->is_active)->toBeTrue()
        ->and($function->manager_id)->toBe($member->id)
        ->and($function->users()->pluck('users.id')->all())->toBe([$member->id])
        ->and($legacyFunction->refresh()->is_active)->toBeFalse()
        ->and($category->business_function_id)->toBe($function->id)
        ->and($category->is_legacy)->toBeFalse()
        ->and($category->requires_quote)->toBeTrue()
        ->and($category->single_quote_per_opportunity)->toBeTrue()
        ->and($category->generates_contract)->toBeTrue()
        ->and($roots->map(fn (ProductCategory $root) => $root->refresh()->only(['business_function_id', 'current_category_id']))->all())
        ->toBe(array_fill(0, 2, ['business_function_id' => $function->id, 'current_category_id' => $category->id]))
        ->and($unrelated->refresh()->business_function_id)->toBe($legacyFunction->id)
        ->and($employment->productLines()->orderBy('id')->get(['business_function_id', 'product_category_id', 'share_percentage'])->toArray())
        ->toBe([
            ['business_function_id' => $gare->id, 'product_category_id' => null, 'share_percentage' => 50],
            ['business_function_id' => $function->id, 'product_category_id' => null, 'share_percentage' => 50],
        ])
        ->and(app(OperatorCompetence::class)->competentUserIds([$legacyChild->id]))->toBe([$member->id]);
});

it('still sets up the function and the categories when the member has no account', function () {
    $legacyFunction = BusinessFunction::factory()->create(['name' => '[OLD] AVVALIMENTI', 'is_active' => false]);
    $root = avvalimentiLegacyRoot($legacyFunction, Seeder::LEGACY_CATEGORY_OLD_IDS[0]);

    $this->seed(Seeder::class);

    $function = BusinessFunction::query()->where('name', Seeder::FUNCTION_NAME)->sole();

    expect($function->manager_id)->toBeNull()
        ->and($function->users()->exists())->toBeFalse()
        ->and($root->refresh()->business_function_id)->toBe($function->id);
});
