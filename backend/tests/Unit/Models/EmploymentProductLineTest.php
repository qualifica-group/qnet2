<?php

use App\Models\BusinessFunction;
use App\Models\EmploymentProductLine;
use App\Models\EmploymentProfile;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Touches the database (migrations, factories), so bind the full TestCase +
// RefreshDatabase explicitly, mirroring OpportunityProductLineTest.

uses(TestCase::class, RefreshDatabase::class);

it('every relation is a BelongsTo to the expected model', function () {
    $line = new EmploymentProductLine;

    expect($line->employmentProfile())->toBeInstanceOf(BelongsTo::class)
        ->and($line->employmentProfile()->getRelated())->toBeInstanceOf(EmploymentProfile::class)
        ->and($line->businessFunction())->toBeInstanceOf(BelongsTo::class)
        ->and($line->businessFunction()->getRelated())->toBeInstanceOf(BusinessFunction::class)
        ->and($line->productCategory())->toBeInstanceOf(BelongsTo::class)
        ->and($line->productCategory()->getRelated())->toBeInstanceOf(ProductCategory::class);
});

/**
 * Spec 0111 D-7: the owner FK stays OUT of the fillables — the field
 * permission catalogue projects each row onto them, and an owner FK there
 * would make a resubmit of unchanged rows look "changed" on a readonly
 * field. The HasMany relation sets it anyway.
 */
it('is mass assignable on the pair only, the owner FK coming from the relation', function () {
    $profile = EmploymentProfile::factory()->create();
    $businessFunction = BusinessFunction::factory()->create();
    $productCategory = ProductCategory::factory()->create();

    expect((new EmploymentProductLine)->getFillable())
        ->toBe(['business_function_id', 'product_category_id']);

    $line = $profile->productLines()->create([
        'employment_profile_id' => $profile->id + 1,
        'business_function_id' => $businessFunction->id,
        'product_category_id' => $productCategory->id,
    ]);

    expect($line->exists)->toBeTrue()
        ->and($line->employmentProfile->is($profile))->toBeTrue()
        ->and($line->businessFunction->is($businessFunction))->toBeTrue()
        ->and($line->productCategory->is($productCategory))->toBeTrue();
});
