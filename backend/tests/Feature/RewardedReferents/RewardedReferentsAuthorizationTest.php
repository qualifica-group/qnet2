<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// AC-012 — `rewarded-referents.viewAny` and `referents.viewAny` are
// INDEPENDENT permission sets: holding the latter alone must NOT unlock this
// module's rows/columns endpoints (the fail-safe RewardedReferentsTableDefinition::
// authorizeViewAny() override this proves).

it('POST /rows returns 403 without rewarded-referents.viewAny, even with referents.viewAny (AC-012)', function () {
    Permission::findOrCreate('referents.viewAny');
    Permission::findOrCreate('rewarded-referents.viewAny');

    $actor = User::factory()->create();
    $actor->givePermissionTo('referents.viewAny');

    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/rewarded-referents/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertForbidden();
});

it('GET /columns returns 403 without rewarded-referents.viewAny, even with referents.viewAny (AC-012)', function () {
    Permission::findOrCreate('referents.viewAny');
    Permission::findOrCreate('rewarded-referents.viewAny');

    $actor = User::factory()->create();
    $actor->givePermissionTo('referents.viewAny');

    Sanctum::actingAs($actor);

    $this->getJson('/api/tables/rewarded-referents/columns')
        ->assertForbidden();
});

it('POST /rows and GET /columns succeed with rewarded-referents.viewAny (AC-012)', function () {
    $actor = rewardedReferentUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/rewarded-referents/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $this->getJson('/api/tables/rewarded-referents/columns')->assertOk();
});
