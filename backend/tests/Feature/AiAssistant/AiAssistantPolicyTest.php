<?php

use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function aiUser(bool $withPermission = true): User
{
    Permission::findOrCreate('ai-assistant.use');
    $user = User::factory()->create();

    if ($withPermission) {
        $user->givePermissionTo('ai-assistant.use');
    }

    return $user;
}

it('lets the owner with the permission view, update and delete', function () {
    $user = aiUser();
    $conversation = AiConversation::factory()->for($user)->create();

    expect($user->can('viewAny', AiConversation::class))->toBeTrue()
        ->and($user->can('create', AiConversation::class))->toBeTrue()
        ->and($user->can('view', $conversation))->toBeTrue()
        ->and($user->can('update', $conversation))->toBeTrue()
        ->and($user->can('delete', $conversation))->toBeTrue();
});

it('denies another user the conversation even with the permission', function () {
    $conversation = AiConversation::factory()->for(aiUser())->create();
    $other = aiUser();

    expect($other->can('viewAny', AiConversation::class))->toBeTrue()
        ->and($other->can('view', $conversation))->toBeFalse()
        ->and($other->can('update', $conversation))->toBeFalse()
        ->and($other->can('delete', $conversation))->toBeFalse();
});

it('denies the owner everything without the permission', function () {
    $user = aiUser(false);
    $conversation = AiConversation::factory()->for($user)->create();

    expect($user->can('viewAny', AiConversation::class))->toBeFalse()
        ->and($user->can('create', AiConversation::class))->toBeFalse()
        ->and($user->can('view', $conversation))->toBeFalse()
        ->and($user->can('delete', $conversation))->toBeFalse();
});
