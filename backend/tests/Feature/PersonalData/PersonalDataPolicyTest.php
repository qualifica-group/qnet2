<?php

use App\Models\PersonalData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('denies CRUD on personal_data when the user lacks the permission', function () {
    $user = User::factory()->create();
    $card = PersonalData::factory()->create();

    expect($user->can('viewAny', PersonalData::class))->toBeFalse()
        ->and($user->can('update', $card))->toBeFalse()
        ->and($user->can('delete', $card))->toBeFalse();
});

it('allows a personal_data ability mapped to the matching permission', function () {
    Permission::create(['name' => 'personal_data.update']);
    $user = User::factory()->create();
    $user->givePermissionTo('personal_data.update');
    $card = PersonalData::factory()->create();

    expect($user->can('update', $card))->toBeTrue()
        ->and($user->can('delete', $card))->toBeFalse();
});

it('permissions:sync registers the standard CRUD permissions for personal_data', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
        expect(Permission::where('name', "personal_data.{$ability}")->exists())->toBeTrue();
    }
});
