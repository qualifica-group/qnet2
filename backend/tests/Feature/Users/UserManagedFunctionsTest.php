<?php

use App\Models\BusinessFunction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// Spec 0276: "Responsabile delle funzioni" is written from the user side
// (`employment.managed_business_function_ids` -> business_functions.manager_id).

/**
 * @param  array<int, string>  $permissions
 */
function managedFunctionsActor(array $permissions = ['users.update', 'business-functions.update']): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission);
    }

    return User::factory()->create()->givePermissionTo($permissions);
}

/** An existing person with a profile and a function membership (the active-user rule of spec 0275). */
function managedFunctionsTarget(): User
{
    $target = User::factory()->withEmployment()->create();
    $target->businessFunctions()->attach(BusinessFunction::factory()->create()->id);

    return $target;
}

it('0276 AC-002: writes manager_id on the listed functions and clears the ones no longer listed', function () {
    $target = managedFunctionsTarget();
    [$f1, $f2, $dropped] = BusinessFunction::factory()->count(3)->create();
    $dropped->update(['manager_id' => $target->id]);
    Sanctum::actingAs(managedFunctionsActor());

    $response = $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['managed_business_function_ids' => [$f1->id, $f2->id]],
    ])->assertOk();

    expect($f1->fresh()->manager_id)->toBe($target->id)
        ->and($f2->fresh()->manager_id)->toBe($target->id)
        ->and($dropped->fresh()->manager_id)->toBeNull()
        ->and(collect($response->json('data.managed_business_functions'))->pluck('id')->all())
        ->toEqualCanonicalizing([$f1->id, $f2->id]);
});

it('0276 AC-003: takes a function from its previous manager and logs the change on the function', function () {
    $target = managedFunctionsTarget();
    $previous = User::factory()->create();
    $function = BusinessFunction::factory()->create(['manager_id' => $previous->id]);
    $actor = managedFunctionsActor();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['managed_business_function_ids' => [$function->id]],
    ])->assertOk();

    expect($function->fresh()->manager_id)->toBe($target->id)
        ->and($previous->managedBusinessFunctions()->count())->toBe(0);

    $activity = Activity::query()->forSubject($function)->where('event', 'updated')->latest('id')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($actor->id)
        ->and($activity->properties->toArray())->toMatchArray(['attributes' => ['manager_id' => $target->id]]);
});

it('0276: an empty array leaves the person without managed functions', function () {
    $target = managedFunctionsTarget();
    $function = BusinessFunction::factory()->create(['manager_id' => $target->id]);
    Sanctum::actingAs(managedFunctionsActor());

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['managed_business_function_ids' => []]])
        ->assertOk()
        ->assertJsonPath('data.managed_business_functions', []);

    expect($function->fresh()->manager_id)->toBeNull();
});

it('0276 AC-004: absent or null leaves every manager untouched', function (array $employment) {
    $target = managedFunctionsTarget();
    $function = BusinessFunction::factory()->create(['manager_id' => $target->id]);
    Sanctum::actingAs(managedFunctionsActor());

    $this->patchJson("/api/users/{$target->id}", ['employment' => $employment])->assertOk();

    expect($function->fresh()->manager_id)->toBe($target->id);
})->with([
    'key absent' => [['standard_daily_minutes' => 480]],
    'explicit null' => [['managed_business_function_ids' => null]],
]);

it('0276 AC-005: without the function permission a changed value is refused and an identical one passes', function () {
    $target = managedFunctionsTarget();
    $managed = BusinessFunction::factory()->create(['manager_id' => $target->id]);
    $other = BusinessFunction::factory()->create();
    Sanctum::actingAs(managedFunctionsActor(['users.update', 'users.view']));

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['managed_business_function_ids' => [$managed->id, $other->id]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employment.managed_business_function_ids']);

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['managed_business_function_ids' => [$managed->id]]])
        ->assertOk();

    expect($other->fresh()->manager_id)->toBeNull()
        ->and($managed->fresh()->manager_id)->toBe($target->id);
});

it('0276 AC-005: exposes the computed field permission to the frontend', function (array $permissions, bool $editable) {
    $target = managedFunctionsTarget();
    Sanctum::actingAs(managedFunctionsActor($permissions));

    $fields = $this->getJson("/api/users/{$target->id}")->assertOk()->json('permissions.fields');

    expect($fields['employment.managed_business_function_ids']['visible'])->toBeTrue()
        ->and($fields['employment.managed_business_function_ids']['editable'])->toBe($editable);
})->with([
    'both permissions' => [['users.update', 'users.view', 'business-functions.update'], true],
    'users only' => [['users.update', 'users.view'], false],
    'functions only' => [['users.view', 'business-functions.update'], false],
]);

it('0276 AC-006: an unknown or repeated id is a 422 on the row', function (array $ids, string $errorKey) {
    $target = managedFunctionsTarget();
    $function = BusinessFunction::factory()->create();
    Sanctum::actingAs(managedFunctionsActor());

    $payload = array_map(fn ($id) => $id ?? $function->id, $ids);

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['managed_business_function_ids' => $payload]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$errorKey]);

    expect($function->fresh()->manager_id)->toBeNull();
})->with([
    'unknown id' => [[999999], 'employment.managed_business_function_ids.0'],
    'duplicate id' => [[null, null], 'employment.managed_business_function_ids.0'],
]);

it('0276 D-1: the request key is_manager is ignored and never echoed', function () {
    $target = managedFunctionsTarget();
    Sanctum::actingAs(managedFunctionsActor());

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['is_manager' => true]])
        ->assertOk()
        ->assertJsonMissingPath('data.employment.is_manager');
});
