<?php

use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * GET /api/users/{user} — the employment tree's multi-site membership (spec
 * 0103). Split out of UserCrudTest.php to keep that file under the 500-line
 * hard limit (engineering.md §6).
 */
if (! function_exists('userWithUserAbilities')) {
    function userWithUserAbilities(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("users.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("users.{$ability}");
        }

        return $user;
    }
}

/**
 * AC-009: the response carries the two site ids plus their two reference
 * objects, and the old single-site keys are gone for good.
 */
it('view: employment carries the physical/remote Sede ids and references, not the old operational_site key (AC-009)', function () {
    $actor = userWithUserAbilities(['view']);
    $target = User::factory()->create();
    $physical = OperationalSite::factory()->withAddress()->create();
    $remote = OperationalSite::factory()->withAddress()->create();
    EmploymentProfile::factory()
        ->physicalSite($physical)
        ->remoteSites($remote)
        ->create(['user_id' => $target->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/users/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.employment.primary_operational_site_id', $physical->id)
        ->assertJsonPath('data.employment.remote_operational_site_ids', [$remote->id])
        ->assertJsonPath('data.employment.primary_operational_site.id', $physical->id)
        ->assertJsonCount(1, 'data.employment.remote_operational_sites')
        ->assertJsonPath('data.employment.remote_operational_sites.0.id', $remote->id)
        ->assertJsonMissingPath('data.employment.operational_site_id')
        ->assertJsonMissingPath('data.employment.operational_site');

    expect($response->json('data.employment.primary_operational_site.label'))->not->toBe('');
});

it('view: employment omits the reference objects when no Sede is a member', function () {
    $actor = userWithUserAbilities(['view']);
    $target = User::factory()->create();
    EmploymentProfile::factory()->create(['user_id' => $target->id]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/users/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.employment.primary_operational_site_id', null)
        ->assertJsonPath('data.employment.remote_operational_site_ids', [])
        ->assertJsonMissingPath('data.employment.primary_operational_site')
        ->assertJsonCount(0, 'data.employment.remote_operational_sites');
});
