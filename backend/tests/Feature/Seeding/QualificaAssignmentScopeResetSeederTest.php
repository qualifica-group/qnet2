<?php

use App\Models\BusinessFunction;
use App\Models\EmploymentProductLine;
use App\Models\EmploymentProfile;
use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\QualificaAssignmentScopeResetSeeder;
use Database\Seeders\QualificaCatalog\OperatorRoster;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return list<array{0: int, 1: int|null}>
 */
function scopePairsOf(User $user): array
{
    return $user->employment()->sole()->productLines()->orderBy('id')->get()
        ->map(fn (EmploymentProductLine $line): array => [$line->business_function_id, $line->product_category_id])
        ->all();
}

it('wipes every scope and wildcard, then rebuilds the roster and function-wide scopes', function () {
    // The functions the legacy import would have left.
    $unassignable = BusinessFunction::factory()->create(['name' => OperatorRoster::UNASSIGNABLE_FUNCTION]);
    $wideEmail = array_key_first(OperatorRoster::FUNCTION_WIDE_COMPETENCE);
    $wide = BusinessFunction::factory()->create(['name' => OperatorRoster::FUNCTION_WIDE_COMPETENCE[$wideEmail]]);
    [, , $plainEmail, , , , , $plainCategories] = collect(OperatorRoster::OPERATORS)
        ->first(fn (array $row): bool => $row[7] === [] && ! isset(OperatorRoster::FUNCTION_WIDE_COMPETENCE[$row[2]]));
    expect($plainCategories)->toBe([]);

    $staleLine = fn (EmploymentProfile $employment) => EmploymentProductLine::factory()->for($employment)->create([
        'business_function_id' => BusinessFunction::factory(),
        'product_category_id' => ProductCategory::factory(),
    ]);

    $wideOperator = EmploymentProfile::factory()->for(User::factory()->create(['email' => $wideEmail]))->create();
    $staleLine($wideOperator);
    $plainOperator = User::factory()->create(['email' => $plainEmail]);
    $unlisted = EmploymentProfile::factory()->create();
    $staleLine($unlisted);
    $wildcard = EmploymentProfile::factory()->create(['covers_all_product_categories' => true]);
    $member = User::factory()->create();
    $memberFunction = BusinessFunction::factory()->create();
    $memberFunction->users()->attach($member);

    $this->seed(QualificaAssignmentScopeResetSeeder::class);

    expect(scopePairsOf($wideOperator->user))->toBe([[$wide->id, null]])
        ->and(scopePairsOf($plainOperator))->toBe([[$unassignable->id, null]])
        ->and(scopePairsOf($unlisted->user))->toBe([])
        ->and($wildcard->fresh()->covers_all_product_categories)->toBeFalse()
        ->and(scopePairsOf($member))->toBe([[$memberFunction->id, null]]);
});

it('converges on a re-run', function () {
    $member = User::factory()->create();
    $function = BusinessFunction::factory()->create();
    $function->users()->attach($member);

    $this->seed(QualificaAssignmentScopeResetSeeder::class);
    $this->seed(QualificaAssignmentScopeResetSeeder::class);

    expect(scopePairsOf($member))->toBe([[$function->id, null]])
        ->and(EmploymentProductLine::query()->count())->toBe(1);
});
