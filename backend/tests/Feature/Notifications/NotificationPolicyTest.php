<?php

declare(strict_types=1);

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

// D-1: viewAny is true for every authenticated user, no Spatie permission
// involved; view/update are ownership-only.

it('viewAny is true for a user with zero permissions', function () {
    $user = User::factory()->create();

    expect(Gate::forUser($user)->allows('viewAny', Notification::class))->toBeTrue();
});

it('view/update are granted on the actor\'s own notification, denied on another\'s', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $notification = Notification::factory()->forUser($owner)->create();

    expect(Gate::forUser($owner)->allows('view', $notification))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('update', $notification))->toBeTrue()
        ->and(Gate::forUser($stranger)->allows('view', $notification))->toBeFalse()
        ->and(Gate::forUser($stranger)->allows('update', $notification))->toBeFalse();
});
