<?php

use App\Models\BusinessFunction;
use App\Models\EmploymentProductLine;
use App\Models\EmploymentProfile;
use App\Models\ProductCategory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Spec 0111 (T1): schema coverage of `employment_product_lines` and of the
 * `employment_profiles.business_function_id` drop (D-1) — the guarantees the
 * competence rows rest on, asserted on the migrations themselves.
 */
it('creates employment_product_lines with the owner FK and both lookup FKs', function () {
    expect(Schema::hasTable('employment_product_lines'))->toBeTrue()
        ->and(Schema::hasColumns('employment_product_lines', [
            'id',
            'employment_profile_id',
            'business_function_id',
            'product_category_id',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
});

it('D-1: employment_profiles no longer carries business_function_id', function () {
    expect(Schema::hasColumn('employment_profiles', 'business_function_id'))->toBeFalse();
});

it('rejects the same (profile, function, category) triple twice', function () {
    $line = EmploymentProductLine::factory()->create();

    expect(fn () => EmploymentProductLine::factory()->create([
        'employment_profile_id' => $line->employment_profile_id,
        'business_function_id' => $line->business_function_id,
        'product_category_id' => $line->product_category_id,
    ]))->toThrow(QueryException::class);
});

it('accepts the same function paired with a different category on the same profile', function () {
    $line = EmploymentProductLine::factory()->create();

    EmploymentProductLine::factory()->create([
        'employment_profile_id' => $line->employment_profile_id,
        'business_function_id' => $line->business_function_id,
        'product_category_id' => ProductCategory::factory()->create()->id,
    ]);

    expect(EmploymentProductLine::where('employment_profile_id', $line->employment_profile_id)->count())->toBe(2);
});

it('cascades the rows when the employment profile is deleted, leaving the lookups intact', function () {
    $line = EmploymentProductLine::factory()->create();

    EmploymentProfile::query()->whereKey($line->employment_profile_id)->first()->delete();

    expect(DB::table('employment_product_lines')->where('id', $line->id)->exists())->toBeFalse()
        ->and(BusinessFunction::query()->whereKey($line->business_function_id)->exists())->toBeTrue()
        ->and(ProductCategory::query()->whereKey($line->product_category_id)->exists())->toBeTrue();
});

it('restricts the deletion of a business function or a product category still used by a row', function () {
    $line = EmploymentProductLine::factory()->create();

    expect(fn () => BusinessFunction::query()->whereKey($line->business_function_id)->first()->delete())
        ->toThrow(QueryException::class);
    expect(fn () => ProductCategory::query()->whereKey($line->product_category_id)->first()->delete())
        ->toThrow(QueryException::class);
});
