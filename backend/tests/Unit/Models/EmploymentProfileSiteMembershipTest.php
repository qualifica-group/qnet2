<?php

use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Touches the database (migrations, factories), so bind the full TestCase +
// RefreshDatabase explicitly (the default Pest binding only applies to the
// Feature suite — see tests/Pest.php).
uses(TestCase::class, RefreshDatabase::class);

/**
 * EmploymentProfileFactory::definition() still seeds a stale
 * 'operational_site_id' key (removed by microtask M11, out of scope here).
 * Factories build via Model::unguarded(), which bypasses the $fillable guard
 * that would otherwise silently drop it, so the raw attribute survives onto
 * a column M2 already dropped and the insert fails. Strip it before
 * persisting instead of touching the factory (not owned by this microtask).
 */
function createEmploymentProfile(array $overrides = []): EmploymentProfile
{
    $profile = EmploymentProfile::factory()->make($overrides);
    unset($profile->operational_site_id);
    $profile->save();

    return $profile;
}

// ---------------------------------------------------------------------------
// Spec 0103 — pivot relations
// ---------------------------------------------------------------------------

it('operationalSites() is a BelongsToMany carrying the is_primary pivot flag', function () {
    $profile = createEmploymentProfile();
    $primary = OperationalSite::factory()->create();
    $remote = OperationalSite::factory()->create();

    $profile->operationalSites()->attach([
        $primary->id => ['is_primary' => true],
        $remote->id => ['is_primary' => false],
    ]);

    $relation = $profile->operationalSites();
    $sites = $profile->operationalSites;

    // Pivot booleans round-trip as raw ints on some drivers (SQLite/MySQL
    // TINYINT); the (bool) cast mirrors what the accessors below do
    // internally, not a test-only concession.
    expect($relation)->toBeInstanceOf(BelongsToMany::class)
        ->and($sites)->toHaveCount(2)
        ->and((bool) $sites->firstWhere('id', $primary->id)->pivot->is_primary)->toBeTrue()
        ->and((bool) $sites->firstWhere('id', $remote->id)->pivot->is_primary)->toBeFalse();
});

it('primaryOperationalSite() restricts to the is_primary=true row', function () {
    $profile = createEmploymentProfile();
    $primary = OperationalSite::factory()->create();
    $remote = OperationalSite::factory()->create();

    $profile->operationalSites()->attach([
        $primary->id => ['is_primary' => true],
        $remote->id => ['is_primary' => false],
    ]);

    $result = $profile->primaryOperationalSite()->get();

    expect($result)->toHaveCount(1)
        ->and($result->first()->is($primary))->toBeTrue();
});

it('remoteOperationalSites() restricts to the is_primary=false rows', function () {
    $profile = createEmploymentProfile();
    $primary = OperationalSite::factory()->create();
    $remoteA = OperationalSite::factory()->create();
    $remoteB = OperationalSite::factory()->create();

    $profile->operationalSites()->attach([
        $primary->id => ['is_primary' => true],
        $remoteA->id => ['is_primary' => false],
        $remoteB->id => ['is_primary' => false],
    ]);

    $result = $profile->remoteOperationalSites()->get();

    expect($result->pluck('id')->all())->toEqualCanonicalizing([$remoteA->id, $remoteB->id]);
});

// ---------------------------------------------------------------------------
// Spec 0103 D-9 — field-permission accessors
// ---------------------------------------------------------------------------

it('primaryOperationalSiteId is null when the profile has no physical site', function () {
    $profile = createEmploymentProfile();
    $remote = OperationalSite::factory()->create();

    $profile->operationalSites()->attach($remote->id, ['is_primary' => false]);

    expect($profile->fresh()->primaryOperationalSiteId)->toBeNull();
});

it('primaryOperationalSiteId resolves the physical site id', function () {
    $profile = createEmploymentProfile();
    $primary = OperationalSite::factory()->create();
    $remote = OperationalSite::factory()->create();

    $profile->operationalSites()->attach([
        $primary->id => ['is_primary' => true],
        $remote->id => ['is_primary' => false],
    ]);

    expect($profile->fresh()->primaryOperationalSiteId)->toBe($primary->id);
});

it('remoteOperationalSiteIds is an empty array when the profile has no remote sites', function () {
    $profile = createEmploymentProfile();
    $primary = OperationalSite::factory()->create();

    $profile->operationalSites()->attach($primary->id, ['is_primary' => true]);

    expect($profile->fresh()->remoteOperationalSiteIds)->toBe([]);
});

it('remoteOperationalSiteIds resolves the remote site ids', function () {
    $profile = createEmploymentProfile();
    $primary = OperationalSite::factory()->create();
    $remoteA = OperationalSite::factory()->create();
    $remoteB = OperationalSite::factory()->create();

    $profile->operationalSites()->attach([
        $primary->id => ['is_primary' => true],
        $remoteA->id => ['is_primary' => false],
        $remoteB->id => ['is_primary' => false],
    ]);

    expect($profile->fresh()->remoteOperationalSiteIds)->toEqualCanonicalizing([$remoteA->id, $remoteB->id]);
});

it('the accessors read off an eager-loaded operationalSites collection without an extra query', function () {
    $profile = createEmploymentProfile();
    $primary = OperationalSite::factory()->create();
    $remote = OperationalSite::factory()->create();

    $profile->operationalSites()->attach([
        $primary->id => ['is_primary' => true],
        $remote->id => ['is_primary' => false],
    ]);

    $loaded = EmploymentProfile::with('operationalSites')->find($profile->id);

    DB::enableQueryLog();

    expect($loaded->primaryOperationalSiteId)->toBe($primary->id)
        ->and($loaded->remoteOperationalSiteIds)->toBe([$remote->id]);

    expect(DB::getQueryLog())->toBeEmpty();

    DB::disableQueryLog();
});
