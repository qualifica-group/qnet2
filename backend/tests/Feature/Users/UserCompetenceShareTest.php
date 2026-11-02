<?php

use App\Models\BusinessFunction;
use App\Models\EmploymentProductLine;
use App\Models\EmploymentProfile;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use App\Services\Assignment\CompetenceLineSetValidator;
use App\Services\Assignment\OperatorCompetence;
use Database\Seeders\DemoEmploymentProfileSeeder;
use Database\Seeders\QualificaFunctionCompetenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0346 — the informative share (1..100) on each "Ambito di assegnazione"
 * row: the D-3 backfill, the per-row and total rules, the flag that keeps no
 * row, the matching left untouched (D-1), the field permission and the seeders.
 */
if (! function_exists('shareActor')) {
    /**
     * @param  array<int, string>  $abilities
     * @param  array<string, mixed>|null  $matrixRow
     */
    function shareActor(array $abilities, ?array $matrixRow = null): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("users.{$ability}");
        }

        $role = Role::create(['name' => 'competence-share-role-'.uniqid()]);
        $role->givePermissionTo(array_map(static fn (string $ability): string => "users.{$ability}", $abilities));

        if ($matrixRow !== null) {
            $role->fieldPermissions()->create($matrixRow);
        }

        $actor = User::factory()->create();
        $actor->assignRole($role);

        return $actor;
    }
}

if (! function_exists('shareTarget')) {
    function shareTarget(?BusinessFunction $function = null, ProductCategory ...$categories): User
    {
        $target = User::factory()->create();
        $profile = EmploymentProfile::factory()->for($target)
            ->memberOf($function ?? BusinessFunction::factory()->create());

        if ($function !== null) {
            $profile = $profile->competentIn($function, ...$categories);
        }

        $profile->create();

        return $target;
    }
}

if (! function_exists('sharesOf')) {
    /**
     * @return array<int, int>
     */
    function sharesOf(User $target): array
    {
        return DB::table('employment_product_lines')
            ->where('employment_profile_id', $target->fresh()->employment?->id)
            ->orderBy('id')
            ->pluck('share_percentage')
            ->map(static fn (mixed $share): int => (int) $share)
            ->all();
    }
}

if (! function_exists('shareRow')) {
    /**
     * @return array{business_function_id: int, product_category_id: int|null, share_percentage: mixed}
     */
    function shareRow(BusinessFunction $function, ?ProductCategory $category, mixed $share): array
    {
        return ['business_function_id' => $function->id, 'product_category_id' => $category?->id, 'share_percentage' => $share];
    }
}

it('0346 AC-001: the migration splits 100 evenly over each profile\'s existing rows, in id order', function () {
    $migration = require database_path('migrations/2026_11_15_100000_add_share_percentage_to_employment_product_lines_table.php');
    $function = BusinessFunction::factory()->create();
    $three = EmploymentProfile::factory()->create();
    $one = EmploymentProfile::factory()->create();
    $empty = EmploymentProfile::factory()->create();

    $migration->down();

    foreach ([[$three, 3], [$one, 1]] as [$profile, $count]) {
        foreach (range(1, $count) as $ignored) {
            DB::table('employment_product_lines')->insert([
                'employment_profile_id' => $profile->id,
                'business_function_id' => $function->id,
                'product_category_id' => ProductCategory::factory()->create()->id,
            ]);
        }
    }

    $migration->up();

    expect(sharesOf($three->user))->toBe([34, 33, 33])
        ->and(sharesOf($one->user))->toBe([100])
        ->and(sharesOf($empty->user))->toBe([]);
});

it('0346 AC-002: three rows 50/20/30 are stored and read back with their shares', function () {
    $actor = shareActor(['view', 'update']);
    $training = BusinessFunction::factory()->create();
    $consulting = BusinessFunction::factory()->create();
    $finance = BusinessFunction::factory()->create();
    $target = shareTarget();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['product_lines' => [
        shareRow($training, null, 50),
        shareRow($consulting, null, 20),
        shareRow($finance, null, 30),
    ]]])->assertOk()
        ->assertJsonPath('data.employment.product_lines.0.share_percentage', 50)
        ->assertJsonPath('data.employment.product_lines.1.share_percentage', 20)
        ->assertJsonPath('data.employment.product_lines.2.share_percentage', 30);

    expect(sharesOf($target))->toBe([50, 20, 30]);

    $this->getJson("/api/users/{$target->id}")->assertOk()
        ->assertJsonPath('data.employment.product_lines.0.share_percentage', 50);
});

it('0346 AC-003: a total other than 100 is a 422 on the collection and nothing changes', function (int $first, int $second) {
    $actor = shareActor(['update']);
    $function = BusinessFunction::factory()->create();
    $other = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $target = shareTarget($function, $category);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['product_lines' => [
        shareRow($function, null, $first),
        shareRow($other, null, $second),
    ]]])->assertStatus(422)->assertJsonValidationErrors([
        'employment.product_lines' => __(CompetenceLineSetValidator::SHARE_TOTAL_MESSAGE),
    ]);

    expect(sharesOf($target))->toBe([100]);
})->with([
    'below 100' => [50, 40],
    'above 100' => [60, 50],
]);

it('0346 AC-004: an invalid share is reported on its row only, without the total error', function (mixed $share) {
    $actor = shareActor(['update']);
    $function = BusinessFunction::factory()->create();
    $target = shareTarget();
    Sanctum::actingAs($actor);

    $row = shareRow($function, null, $share);

    if ($share === 'missing') {
        unset($row['share_percentage']);
    }

    $response = $this->patchJson("/api/users/{$target->id}", ['employment' => ['product_lines' => [$row]]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('employment.product_lines.0.share_percentage');

    expect($response->json('errors'))->not->toHaveKey('employment.product_lines')
        ->and(sharesOf($target))->toBe([]);
})->with([
    'missing' => ['missing'],
    'zero' => [0],
    'over 100' => [101],
    'not an integer' => [12.5],
]);

it('0346 AC-007: the "all categories" flag and an emptied set skip the total check', function () {
    $actor = shareActor(['update']);
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $flagged = shareTarget($function, $category);
    $emptied = shareTarget($function, $category);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$flagged->id}", ['employment' => ['covers_all_product_categories' => true, 'product_lines' => []]])
        ->assertOk();
    $this->patchJson("/api/users/{$emptied->id}", ['employment' => ['product_lines' => []]])->assertOk();

    expect(sharesOf($flagged))->toBe([])
        ->and(sharesOf($emptied))->toBe([]);
});

it('0346 AC-008: overlapping rows and their shares leave the matching as the union of the rows', function () {
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $sibling = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $wholeFunction = EmploymentProfile::factory()->competentInEveryCategoryOf($function)->create();
    $overlapping = EmploymentProfile::factory()->competentInEveryCategoryOf($function)->create();
    $overlapping->productLines()->update(['share_percentage' => 70]);
    $overlapping->productLines()->create(['business_function_id' => $function->id, 'product_category_id' => $category->id, 'share_percentage' => 30]);

    $competence = app(OperatorCompetence::class);
    $candidates = [$wholeFunction->user_id, $overlapping->user_id];

    expect($competence->competent($candidates, [$category->id]))->toEqualCanonicalizing($candidates)
        ->and($competence->competent($candidates, [$sibling->id]))->toEqualCanonicalizing($candidates)
        ->and($competence->coveredCategoryIdsFor($overlapping->user_id, [$category->id, $sibling->id]))
        ->toEqualCanonicalizing($competence->coveredCategoryIdsFor($wholeFunction->user_id, [$category->id, $sibling->id]));
});

it('0346 AC-009: on a read-only field the same shares pass, a changed share is refused', function () {
    $actor = shareActor(
        ['view', 'update'],
        ['resource' => 'users', 'field' => 'employment.product_lines', 'visible' => true, 'editable' => false, 'required' => false],
    );
    $function = BusinessFunction::factory()->create();
    $categoryA = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $categoryB = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $target = shareTarget($function, $categoryA, $categoryB);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['product_lines' => [
        shareRow($function, $categoryA, 50),
        shareRow($function, $categoryB, 50),
    ]]])->assertOk();

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['product_lines' => [
        shareRow($function, $categoryA, 60),
        shareRow($function, $categoryB, 40),
    ]]])->assertStatus(422)->assertJsonValidationErrors('employment.product_lines');

    expect(sharesOf($target))->toBe([50, 50]);
});

it('0346 AC-015: the seeders leave every profile with rows at a total of 100', function () {
    $function = BusinessFunction::factory()->create();
    $other = BusinessFunction::factory()->create();
    $employment = EmploymentProfile::factory()->create();
    EmploymentProductLine::factory()->for($employment)->create([
        'business_function_id' => $other->id,
        'product_category_id' => ProductCategory::factory(),
    ]);
    $function->users()->attach($employment->user_id);

    $this->seed(QualificaFunctionCompetenceSeeder::class);
    $this->seed(DemoEmploymentProfileSeeder::class);

    expect(sharesOf($employment->user))->toBe([50, 50]);

    $totals = DB::table('employment_product_lines')
        ->selectRaw('employment_profile_id, SUM(share_percentage) as total')
        ->groupBy('employment_profile_id')
        ->pluck('total')
        ->map(static fn (mixed $total): int => (int) $total)
        ->unique()
        ->values()
        ->all();

    expect($totals)->toBe([EmploymentProductLine::SHARE_TOTAL]);
});
