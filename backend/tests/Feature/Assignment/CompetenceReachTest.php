<?php

use App\Models\BusinessFunction;
use App\Models\EmploymentProfile;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\Assignment\ActorCompetenceReach;
use App\Services\Assignment\CompetenceReach;
use App\Services\Assignment\OperatorCompetence;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Spec 0340 (D-6, D-7, D-8): the reach the `viewCompetence` tier reads.

function reachOfUser(User $user): CompetenceReach
{
    return app(OperatorCompetence::class)->reachOf($user);
}

it('is empty for a user without profile or without competence rows (AC-012)', function (): void {
    $withoutProfile = User::factory()->create();
    $withoutRows = User::factory()->create();
    EmploymentProfile::factory()->create(['user_id' => $withoutRows->id]);

    expect(reachOfUser($withoutProfile)->isEmpty())->toBeTrue()
        ->and(reachOfUser($withoutRows)->isEmpty())->toBeTrue();
});

it('covers the descendants of a parent row and only the categories of the row function (AC-003)', function (): void {
    $function = BusinessFunction::factory()->create();
    $parent = ProductCategory::factory()->create(['business_function_id' => $function->id, 'is_selectable' => false]);
    $child = ProductCategory::factory()->childOf($parent)->create();
    [, $unrelated] = competenceVisibilityCategory();
    $user = User::factory()->create();
    grantCompetence($user, $function, $parent);

    $reach = reachOfUser($user);

    expect($reach->all)->toBeFalse()
        ->and($reach->categoryIds)->toContain($parent->id, $child->id)
        ->and($reach->categoryIds)->not->toContain($unrelated->id)
        ->and($reach->reaches([$child->id]))->toBeTrue()
        ->and($reach->reaches([$unrelated->id]))->toBeFalse()
        ->and($reach->reaches([]))->toBeFalse();
});

it('covers every category of the function on a (function, null) row and no other function (AC-003)', function (): void {
    [$function, $first] = competenceVisibilityCategory();
    [, $second] = competenceVisibilityCategory($function);
    [, $otherFunction] = competenceVisibilityCategory();
    $user = User::factory()->create();
    grantCompetenceOnEveryCategoryOf($user, $function);

    expect(reachOfUser($user)->categoryIds)->toContain($first->id, $second->id)
        ->not->toContain($otherFunction->id);
});

it('reaches everything, records without categories included, with the all-categories flag (D-3)', function (): void {
    $user = User::factory()->create();
    grantCompetenceOnAllCategories($user);

    $reach = reachOfUser($user);

    expect($reach->all)->toBeTrue()
        ->and($reach->reaches([]))->toBeTrue()
        ->and($reach->fingerprint())->toBe('all');
});

it('ignores the assignable switch (AC-011)', function (): void {
    [$function, $category] = competenceVisibilityCategory();
    $user = User::factory()->create();
    grantCompetence($user, $function, $category)->forceFill(['is_assignable' => false])->save();

    expect(reachOfUser($user)->categoryIds)->toContain($category->id);
});

it('fingerprints different reaches differently and memoizes the actor reach per request', function (): void {
    [$function, $first] = competenceVisibilityCategory();
    [, $second] = competenceVisibilityCategory($function);
    $one = User::factory()->create();
    $two = User::factory()->create();
    grantCompetence($one, $function, $first);
    grantCompetence($two, $function, $second);

    expect(reachOfUser($one)->fingerprint())->not->toBe(reachOfUser($two)->fingerprint())
        ->and(CompetenceReach::none()->fingerprint())->toBe('')
        ->and(ActorCompetenceReach::for($one))->toBe(ActorCompetenceReach::for($one));
});
