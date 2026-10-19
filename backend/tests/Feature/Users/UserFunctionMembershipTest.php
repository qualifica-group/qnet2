<?php

use App\Models\BusinessFunction;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0275: the business function membership (pivot `business_function_user`)
 * as one datum, read and written from the user form (`employment.business_function_ids`)
 * and from the business-functions page.
 */
function membershipFieldPermissionActor(bool $editable): User
{
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("users.{$ability}");
    }

    $role = Role::create(['name' => 'membership-role-'.uniqid()]);
    $role->givePermissionTo(['users.view', 'users.update']);
    $role->fieldPermissions()->create([
        'resource' => 'users',
        'field' => 'employment.business_function_ids',
        'visible' => true,
        'editable' => $editable,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    return $actor;
}

it('0275 AC-001: a user added from the business functions page shows the function in employment.business_function_ids', function () {
    $function = BusinessFunction::factory()->create(['name' => 'Sales']);
    $target = User::factory()->withEmployment()->create();
    $function->users()->sync([$target->id]);
    Sanctum::actingAs(userWithUserAbilities(['view']));

    $this->getJson("/api/users/{$target->id}")->assertOk()
        ->assertJsonPath('data.employment.business_function_ids', [$function->id])
        ->assertJsonPath('data.employment.business_functions', [['id' => $function->id, 'name' => 'Sales']]);
});

it('0275 AC-002: writing the user list updates the function members; a removed function loses the user', function () {
    $f1 = BusinessFunction::factory()->create();
    $f2 = BusinessFunction::factory()->create();
    $target = User::factory()->create();
    Sanctum::actingAs(userWithUserAbilities(['update']));

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['business_function_ids' => [$f1->id, $f2->id]]])
        ->assertOk()
        ->assertJsonPath('data.employment.business_function_ids', [$f1->id, $f2->id]);

    expect($f1->users()->pluck('users.id')->all())->toBe([$target->id]);

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['business_function_ids' => [$f2->id]]])->assertOk();

    expect($f1->users()->count())->toBe(0)
        ->and($f2->users()->pluck('users.id')->all())->toBe([$target->id]);
});

it('0275 AC-003: an employment payload without business_function_ids (or null) leaves the membership untouched', function () {
    $function = BusinessFunction::factory()->create();
    $target = User::factory()->withEmployment(fn ($profile) => $profile->memberOf($function))->create();
    Sanctum::actingAs(userWithUserAbilities(['update']));

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['standard_daily_minutes' => 300]])->assertOk();
    $this->patchJson("/api/users/{$target->id}", ['employment' => ['business_function_ids' => null]])->assertOk();

    expect($target->businessFunctions()->pluck('business_functions.id')->all())->toBe([$function->id]);
});

it('0275 AC-009: an unknown or duplicated function id is a 422 on the row', function () {
    $function = BusinessFunction::factory()->create();
    $target = User::factory()->create();
    Sanctum::actingAs(userWithUserAbilities(['update']));

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['business_function_ids' => [$function->id, 999999]]])
        ->assertStatus(422)->assertJsonValidationErrors(['employment.business_function_ids.1']);

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['business_function_ids' => [$function->id, $function->id]]])
        ->assertStatus(422)->assertJsonValidationErrors(['employment.business_function_ids.0']);

    expect($target->businessFunctions()->count())->toBe(0);
});

it('0275 AC-010: a readonly business_function_ids rejects a different value and accepts the identical one', function () {
    $function = BusinessFunction::factory()->create();
    $other = BusinessFunction::factory()->create();
    $target = User::factory()->withEmployment(fn ($profile) => $profile->memberOf($function))->create();
    Sanctum::actingAs(membershipFieldPermissionActor(editable: false));

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['business_function_ids' => [$other->id]]])
        ->assertStatus(422);
    expect($target->businessFunctions()->pluck('business_functions.id')->all())->toBe([$function->id]);

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['business_function_ids' => [$function->id]]])->assertOk();
});

it('0275 AC-011: managed_business_functions lists the functions the person manages, ordered by name', function () {
    $manager = User::factory()->create();
    BusinessFunction::factory()->create(['name' => 'Zeta', 'manager_id' => $manager->id]);
    BusinessFunction::factory()->create(['name' => 'Alpha', 'manager_id' => $manager->id]);
    $plain = User::factory()->create();
    Sanctum::actingAs(userWithUserAbilities(['view']));

    $this->getJson("/api/users/{$manager->id}")->assertOk()
        ->assertJsonPath('data.managed_business_functions.0.name', 'Alpha')
        ->assertJsonPath('data.managed_business_functions.1.name', 'Zeta')
        ->assertJsonCount(2, 'data.managed_business_functions');

    $this->getJson("/api/users/{$plain->id}")->assertOk()
        ->assertJsonPath('data.managed_business_functions', []);
});
