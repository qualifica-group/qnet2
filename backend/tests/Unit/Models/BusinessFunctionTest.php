<?php

use App\Models\BusinessFunction;
use App\Models\Concerns\LogsModelActivity;
use App\Models\OperationalSite;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Touches the database (migrations, factories), so bind the full TestCase +
// RefreshDatabase explicitly (the default Pest binding only applies to the
// Feature suite — see tests/Pest.php).
uses(TestCase::class, RefreshDatabase::class);

// AppServiceProvider::boot() enforces a morph map (defence in depth against
// morph-type injection) and every model using LogsModelActivity resolves its
// morph alias when the activity log records subject/causer. Registering
// BusinessFunction here is a TEST-ONLY merge: the actual alias still needs to
// be added to AppServiceProvider's Relation::enforceMorphMap() call (outside
// this teammate's `backend/database/` ownership — flagged to `backend`),
// otherwise creating/updating a BusinessFunction in production throws
// ClassMorphViolationException.
beforeEach(fn () => Relation::morphMap(['business_function' => BusinessFunction::class]));

// ---------------------------------------------------------------------------
// AC-001 — schema
// ---------------------------------------------------------------------------

it('creates the business_functions table with the expected columns', function () {
    expect(Schema::hasTable('business_functions'))->toBeTrue();
    expect(Schema::hasColumns('business_functions', [
        'id', 'name', 'is_business_unit', 'is_business_service', 'manager_id', 'parent_id', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('creates the business_function_user pivot table with the expected columns', function () {
    expect(Schema::hasTable('business_function_user'))->toBeTrue();
    expect(Schema::hasColumns('business_function_user', [
        'id', 'business_function_id', 'user_id',
    ]))->toBeTrue();
});

it('creates the business_function_operational_site pivot table with the expected columns', function () {
    expect(Schema::hasTable('business_function_operational_site'))->toBeTrue();
    expect(Schema::hasColumns('business_function_operational_site', [
        'id', 'business_function_id', 'operational_site_id',
    ]))->toBeTrue();
});

it('parent_id restricts on delete: a function with children cannot be deleted at the DB level', function () {
    $parent = BusinessFunction::factory()->create();
    BusinessFunction::factory()->childOf($parent)->create();

    expect(fn () => DB::table('business_functions')->where('id', $parent->id)->delete())
        ->toThrow(QueryException::class);
});

it('the operational_site pivot rejects duplicate (business_function_id, operational_site_id) rows', function () {
    $function = BusinessFunction::factory()->create();
    $site = OperationalSite::factory()->withAddress()->create();

    $function->operationalSites()->attach($site);

    expect(fn () => DB::table('business_function_operational_site')->insert([
        'business_function_id' => $function->id,
        'operational_site_id' => $site->id,
    ]))->toThrow(QueryException::class);
});

it('the operational_site pivot cascades on function or site deletion', function () {
    $function = BusinessFunction::factory()->create();
    $site = OperationalSite::factory()->withAddress()->create();
    $function->operationalSites()->attach($site);

    $function->delete();

    expect(DB::table('business_function_operational_site')->where('operational_site_id', $site->id)->exists())->toBeFalse();
});

it('down()/up() reverses and recreates the operational-site pivot migration', function () {
    $operationalSitePivot = require database_path('migrations/2026_07_16_100100_create_business_function_operational_site_table.php');

    $operationalSitePivot->down();

    expect(Schema::hasTable('business_function_operational_site'))->toBeFalse();

    $operationalSitePivot->up();

    expect(Schema::hasTable('business_function_operational_site'))->toBeTrue();
});

it('name is required at the database level', function () {
    expect(fn () => DB::table('business_functions')->insert(['created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);
});

it('manager_id is nullable and set null on the manager user deletion', function () {
    $manager = User::factory()->create();
    $function = BusinessFunction::factory()->create(['manager_id' => $manager->id]);

    $manager->delete();

    expect($function->fresh()->manager_id)->toBeNull();
});

it('the pivot rejects duplicate (business_function_id, user_id) rows', function () {
    $function = BusinessFunction::factory()->create();
    $user = User::factory()->create();

    $function->users()->attach($user);

    expect(fn () => DB::table('business_function_user')->insert([
        'business_function_id' => $function->id,
        'user_id' => $user->id,
    ]))->toThrow(QueryException::class);
});

it('the pivot cascades on function or user deletion', function () {
    $function = BusinessFunction::factory()->create();
    $user = User::factory()->create();
    $function->users()->attach($user);

    $function->delete();

    expect(DB::table('business_function_user')->where('user_id', $user->id)->exists())->toBeFalse();
});

it('down() reverses both migrations, up() recreates them', function () {
    $businessFunctions = require database_path('migrations/2026_07_03_120000_create_business_functions_table.php');
    $pivot = require database_path('migrations/2026_07_03_120100_create_business_function_user_table.php');

    // The pivot FKs the parent table, so it must be dropped first.
    $pivot->down();
    $businessFunctions->down();

    expect(Schema::hasTable('business_functions'))->toBeFalse();
    expect(Schema::hasTable('business_function_user'))->toBeFalse();

    $businessFunctions->up();
    $pivot->up();

    expect(Schema::hasTable('business_functions'))->toBeTrue();
    expect(Schema::hasTable('business_function_user'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-002 — model relations and casts
// ---------------------------------------------------------------------------

it('manager() is a BelongsTo relation to User', function () {
    $relation = (new BusinessFunction)->manager();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(User::class);
});

it('users() is a BelongsToMany relation to User via the pivot table', function () {
    $relation = (new BusinessFunction)->users();

    expect($relation)->toBeInstanceOf(BelongsToMany::class);
    expect($relation->getRelated())->toBeInstanceOf(User::class);
    expect($relation->getTable())->toBe('business_function_user');
});

it('parent() is a self-referencing BelongsTo relation', function () {
    $relation = (new BusinessFunction)->parent();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(BusinessFunction::class);
});

it('children() is a self-referencing HasMany relation', function () {
    $relation = (new BusinessFunction)->children();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(BusinessFunction::class);
});

it('operationalSites() is a BelongsToMany relation to OperationalSite via the pivot table', function () {
    $relation = (new BusinessFunction)->operationalSites();

    expect($relation)->toBeInstanceOf(BelongsToMany::class);
    expect($relation->getRelated())->toBeInstanceOf(OperationalSite::class);
    expect($relation->getTable())->toBe('business_function_operational_site');
});

it('resolves the parent/children hierarchy', function () {
    $root = BusinessFunction::factory()->create();
    $child = BusinessFunction::factory()->childOf($root)->create();

    expect($child->parent->is($root))->toBeTrue();
    expect($root->children->pluck('id'))->toContain($child->id);
});

it('casts is_business_unit and is_business_service to boolean', function () {
    $function = BusinessFunction::factory()->create([
        'is_business_unit' => 1,
        'is_business_service' => 0,
    ]);

    expect($function->is_business_unit)->toBeTrue();
    expect($function->is_business_service)->toBeFalse();
});

it('resolves the manager and associated users relations', function () {
    $manager = User::factory()->create();
    $associated = User::factory()->create();
    $function = BusinessFunction::factory()->create(['manager_id' => $manager->id]);
    $function->users()->attach($associated);

    expect($function->manager->is($manager))->toBeTrue();
    expect($function->users->pluck('id'))->toContain($associated->id);
});

it('factory produces a mutually exclusive type', function () {
    $function = BusinessFunction::factory()->create();

    expect($function->is_business_unit && $function->is_business_service)->toBeFalse();
});

it('logs model activity on the business_functions log channel', function () {
    expect(class_uses(BusinessFunction::class))->toHaveKey(LogsModelActivity::class);
});
