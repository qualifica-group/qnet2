<?php

use App\Models\BusinessFunction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('businessFunctionUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function businessFunctionUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("business-functions.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("business-functions.{$ability}");
        }

        return $user;
    }
}

/**
 * Spec 0275 D-5: the business-functions page cannot strand an active,
 * non-service person without any function.
 */
it('0275 AC-012: removing the only function of an active member is a 422 on users with the name, and nothing is written', function () {
    $function = BusinessFunction::factory()->create();
    $stranded = User::factory()->create(['name' => 'Stranded Person']);
    $function->users()->sync([$stranded->id]);
    Sanctum::actingAs(businessFunctionUserWith(['update']));

    $this->patchJson("/api/business-functions/{$function->id}", ['name' => 'Renamed', 'users' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['users']);

    expect($function->fresh()->name)->not->toBe('Renamed')
        ->and($function->users()->pluck('users.id')->all())->toBe([$stranded->id]);
    expect(collect($this->patchJson("/api/business-functions/{$function->id}", ['users' => []])->json('errors.users')))
        ->each->toContain('Stranded Person');
});

it('0275 AC-012: removal passes for a member with another function, an inactive member or a service account', function () {
    $function = BusinessFunction::factory()->create();
    $elsewhere = User::factory()->create();
    $inactive = User::factory()->inactive()->create();
    $service = User::factory()->serviceAccount()->create();
    $function->users()->sync([$elsewhere->id, $inactive->id, $service->id]);
    BusinessFunction::factory()->create()->users()->sync([$elsewhere->id]);
    Sanctum::actingAs(businessFunctionUserWith(['update']));

    $this->patchJson("/api/business-functions/{$function->id}", ['users' => []])->assertOk();

    expect($function->users()->count())->toBe(0);
});

it('0275 AC-013: deleting a function with an active member lacking other functions is a 409; otherwise 204', function () {
    $blocked = BusinessFunction::factory()->create();
    $stranded = User::factory()->create(['name' => 'Stranded Person']);
    $blocked->users()->sync([$stranded->id]);
    $free = BusinessFunction::factory()->create();
    $covered = User::factory()->create();
    $free->users()->sync([$covered->id]);
    BusinessFunction::factory()->create()->users()->sync([$covered->id]);
    Sanctum::actingAs(businessFunctionUserWith(['delete']));

    $this->deleteJson("/api/business-functions/{$blocked->id}")->assertStatus(409);
    $this->assertDatabaseHas('business_functions', ['id' => $blocked->id]);

    $this->deleteJson("/api/business-functions/{$free->id}")->assertNoContent();
});
