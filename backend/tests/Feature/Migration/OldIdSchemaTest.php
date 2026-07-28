<?php

use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\OperationalSite;
use App\Models\Referent;
use App\Models\ReferentType;
use App\Models\Role;
use App\Models\Sector;
use App\Models\Source;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

// Target tables of the `old_id` column (spec 0013 — external data migration):
// a BIGINT UNSIGNED nullable + unique index on each, declared by the table's own
// create migration and used by the import engine for idempotence/remapping.
dataset('old_id_tables', [
    'users' => ['users'],
    'roles' => ['roles'],
    'business_functions' => ['business_functions'],
    'companies' => ['companies'],
    'operational_sites' => ['operational_sites'],
    'referent_types' => ['referent_types'],
    'referents' => ['referents'],
    'sources' => ['sources'],
    'tags' => ['tags'],
    'sectors' => ['sectors'],
]);

/**
 * A bare (unsaved) factory for the given target table, used to create rows
 * without depending on each entity's own required-field business rules.
 */
function oldIdFactoryFor(string $table): Factory
{
    return match ($table) {
        'users' => User::factory(),
        'roles' => Role::factory(),
        'business_functions' => BusinessFunction::factory(),
        'companies' => Company::factory(),
        'operational_sites' => OperationalSite::factory(),
        'referent_types' => ReferentType::factory(),
        'referents' => Referent::factory(),
        'sources' => Source::factory(),
        'tags' => Tag::factory(),
        'sectors' => Sector::factory(),
    };
}

// ---------------------------------------------------------------------------
// AC-001 — schema, unique, nullable
// ---------------------------------------------------------------------------

it('adds a nullable old_id column', function (string $table) {
    expect(Schema::hasColumn($table, 'old_id'))->toBeTrue();
})->with('old_id_tables');

it('allows NULL old_id for native (non-migrated) rows', function (string $table) {
    oldIdFactoryFor($table)->count(2)->create();

    expect(DB::table($table)->whereNull('old_id')->count())->toBeGreaterThanOrEqual(2);
})->with('old_id_tables');

it('rejects a duplicate non-null old_id on the same table', function (string $table) {
    $factory = oldIdFactoryFor($table);

    $factory->create(['old_id' => 42]);

    expect(fn () => $factory->create(['old_id' => 42]))->toThrow(QueryException::class);
})->with('old_id_tables');

// ---------------------------------------------------------------------------
// AC-002 — set-by-property persists, mass-assignment is guarded
// ---------------------------------------------------------------------------

it('persists old_id set by property assignment on User', function () {
    $user = User::factory()->create();
    $user->old_id = 501;
    $user->save();

    expect($user->fresh()->old_id)->toBe(501);
});

it('does not mass-assign old_id on User::create()', function () {
    $user = User::create([
        'name' => 'Jane Doe',
        'email' => 'jane.doe@example.com',
        'password' => 'a-secret-password',
        'old_id' => 999,
    ]);

    expect($user->old_id)->toBeNull();
});

it('persists old_id set by property assignment on BusinessFunction', function () {
    $function = BusinessFunction::factory()->create();
    $function->old_id = 502;
    $function->save();

    expect($function->fresh()->old_id)->toBe(502);
});

it('does not mass-assign old_id on BusinessFunction::create()', function () {
    $function = BusinessFunction::create(['name' => 'Legal', 'old_id' => 999]);

    expect($function->old_id)->toBeNull();
});

it('persists old_id set by property assignment on Company', function () {
    $company = Company::factory()->create();
    $company->old_id = 503;
    $company->save();

    expect($company->fresh()->old_id)->toBe(503);
});

it('does not mass-assign old_id on Company::create()', function () {
    $company = Company::create(['denomination' => 'Acme Srl', 'old_id' => 999]);

    expect($company->old_id)->toBeNull();
});

it('persists old_id set by property assignment on OperationalSite', function () {
    $site = OperationalSite::factory()->create();
    $site->old_id = 504;
    $site->save();

    expect($site->fresh()->old_id)->toBe(504);
});

it('does not mass-assign old_id on OperationalSite::create()', function () {
    // OperationalSite now has one own writable column (`alias`): the guard
    // silently drops old_id (not in $fillable) while accepting alias, matching
    // Company/Role above. old_id stays settable only by property assignment.
    $site = OperationalSite::create(['alias' => 'HQ', 'old_id' => 999]);

    expect($site->old_id)->toBeNull()
        ->and($site->alias)->toBe('HQ');
});

it('does not mass-assign old_id on Role::create()', function () {
    $role = Role::create(['name' => 'imported-role', 'guard_name' => 'web', 'old_id' => 999]);

    expect($role->old_id)->toBeNull();
});

it('persists old_id set by property assignment on ReferentType', function () {
    $referentType = ReferentType::factory()->create();
    $referentType->old_id = 505;
    $referentType->save();

    expect($referentType->fresh()->old_id)->toBe(505);
});

it('does not mass-assign old_id on ReferentType::create()', function () {
    $referentType = ReferentType::create(['name' => 'Supplier', 'old_id' => 999]);

    expect($referentType->old_id)->toBeNull();
});

it('persists old_id set by property assignment on Referent', function () {
    $referent = Referent::factory()->create();
    $referent->old_id = 506;
    $referent->save();

    expect($referent->fresh()->old_id)->toBe(506);
});

it('does not mass-assign old_id on Referent::create()', function () {
    $referent = Referent::create(['name' => 'John Doe', 'contact_scope' => 'internal', 'old_id' => 999]);

    expect($referent->old_id)->toBeNull();
});

it('persists old_id set by property assignment on Source', function () {
    $source = Source::factory()->create();
    $source->old_id = 507;
    $source->save();

    expect($source->fresh()->old_id)->toBe(507);
});

it('does not mass-assign old_id on Source::create()', function () {
    $source = Source::create(['name' => 'Website', 'old_id' => 999]);

    expect($source->old_id)->toBeNull();
});

it('persists old_id set by property assignment on Tag', function () {
    $tag = Tag::factory()->create();
    $tag->old_id = 508;
    $tag->save();

    expect($tag->fresh()->old_id)->toBe(508);
});

it('does not mass-assign old_id on Tag::create()', function () {
    $tag = Tag::create(['name' => 'Prospect', 'old_id' => 999]);

    expect($tag->old_id)->toBeNull();
});

it('persists old_id set by property assignment on Sector', function () {
    $sector = Sector::factory()->create();
    $sector->old_id = 509;
    $sector->save();

    expect($sector->fresh()->old_id)->toBe(509);
});

it('does not mass-assign old_id on Sector::create()', function () {
    $sector = Sector::create(['name' => 'Manufacturing', 'old_id' => 999]);

    expect($sector->old_id)->toBeNull();
});
