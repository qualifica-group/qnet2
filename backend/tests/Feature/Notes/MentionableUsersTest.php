<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// D-10: the mentionable set is active users who can read the record — its
// GA2 managers WITH request-management.view, holders of
// request-management.viewAll, and super-admins. Inactive users, users
// without request-management.view, and outsiders never appear (AC-050).

uses(RefreshDatabase::class);

if (! function_exists('noteActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function noteActor(array $abilities = []): User
    {
        foreach (['request-management.view', 'request-management.viewAll', 'notes.create'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo($ability);
        }

        return $user;
    }
}

if (! function_exists('noteManagedOpportunity')) {
    // Spec 0086, D-9: read/mention access is re-keyed on the Opportunity's
    // own Offerte (D-3, `quotes.supervisor_id`), not the GA2 pivot slot.
    function noteManagedOpportunity(User $supervisor): Opportunity
    {
        $opportunity = Opportunity::factory()->create();
        Quote::factory()->for($opportunity)->create(['supervisor_id' => $supervisor->id]);

        return $opportunity;
    }
}

it('returns only active users who can read the record: manager+view, viewAll holders, super-admins (AC-050)', function () {
    $actor = noteActor(['request-management.view']);
    $opportunity = noteManagedOpportunity($actor);

    $outsiderWithoutView = User::factory()->create();

    $viewAllHolder = noteActor(['request-management.view', 'request-management.viewAll']);

    // opportunity_user has a UNIQUE(opportunity_id, position) constraint, so
    // this user qualifies via viewAll (not the already-taken manager pivot)
    // — is_active=false must still exclude them regardless of that branch.
    $inactiveViewAllHolder = User::factory()->create(['is_active' => false]);
    $inactiveViewAllHolder->givePermissionTo(['request-management.view', 'request-management.viewAll']);

    Role::create(['name' => 'super-admin']);
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/notes/mentionable-users?entity_type=request-management&entity_id={$opportunity->id}")
        ->assertOk();

    $ids = collect($response->json('items'))->pluck('id')->all();

    expect($ids)->toContain($actor->id, $viewAllHolder->id, $superAdmin->id);
    expect($ids)->not->toContain($outsiderWithoutView->id, $inactiveViewAllHolder->id);
});

it('search filters mentionable users by name (AC-050)', function () {
    $actor = noteActor(['request-management.view']);
    $opportunity = noteManagedOpportunity($actor);

    $findMe = User::factory()->create(['name' => 'Zzz Findme']);
    $findMe->givePermissionTo(['request-management.view', 'request-management.viewAll']);

    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/notes/mentionable-users?entity_type=request-management&entity_id={$opportunity->id}&search=Findme")
        ->assertOk();

    expect(collect($response->json('items'))->pluck('id')->all())->toBe([$findMe->id]);
});

it('a user without read access to the record -> 403 on the mentionable-users lookup (D-6)', function () {
    $outsider = noteActor([]);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($outsider);

    $this->getJson("/api/notes/mentionable-users?entity_type=request-management&entity_id={$opportunity->id}")
        ->assertForbidden();
});
