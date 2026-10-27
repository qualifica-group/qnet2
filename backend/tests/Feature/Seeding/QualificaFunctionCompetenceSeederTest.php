<?php

use App\Models\BusinessFunction;
use App\Models\EmploymentProductLine;
use App\Models\EmploymentProfile;
use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\QualificaFunctionCompetenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return list<array{0: int, 1: int|null}>
 */
function competencePairsOf(User $user): array
{
    return $user->employment()->sole()->productLines()->orderBy('id')->get()
        ->map(fn (EmploymentProductLine $line): array => [$line->business_function_id, $line->product_category_id])
        ->all();
}

it('gives a member without a profile the function on every category', function () {
    $function = BusinessFunction::factory()->create();
    $user = User::factory()->create();
    $function->users()->attach($user);

    $this->seed(QualificaFunctionCompetenceSeeder::class);

    expect(competencePairsOf($user))->toBe([[$function->id, null]]);
});

it('adds the row next to the scopes already there and replaces the function\'s own category rows', function () {
    $function = BusinessFunction::factory()->create();
    $other = BusinessFunction::factory()->create();
    $employment = EmploymentProfile::factory()->create();
    $kept = EmploymentProductLine::factory()->for($employment)->create([
        'business_function_id' => $other->id,
        'product_category_id' => ProductCategory::factory(),
    ]);
    EmploymentProductLine::factory()->for($employment)->create([
        'business_function_id' => $function->id,
        'product_category_id' => ProductCategory::factory(),
    ]);
    $function->users()->attach($employment->user_id);

    $this->seed(QualificaFunctionCompetenceSeeder::class);
    $this->seed(QualificaFunctionCompetenceSeeder::class);

    expect(competencePairsOf($employment->user))->toBe([
        [$other->id, $kept->product_category_id],
        [$function->id, null],
    ]);
});

it('leaves alone the "all categories" profiles, the inactive functions and the non-members', function () {
    $inactive = BusinessFunction::factory()->inactive()->create();
    $function = BusinessFunction::factory()->create();
    $wildcard = EmploymentProfile::factory()->create(['covers_all_product_categories' => true]);
    $inactiveMember = EmploymentProfile::factory()->create();
    $nonMember = EmploymentProfile::factory()->create();
    $function->users()->attach($wildcard->user_id);
    $inactive->users()->attach($inactiveMember->user_id);

    $this->seed(QualificaFunctionCompetenceSeeder::class);

    expect(competencePairsOf($wildcard->user))->toBe([])
        ->and(competencePairsOf($inactiveMember->user))->toBe([])
        ->and(competencePairsOf($nonMember->user))->toBe([]);
});
