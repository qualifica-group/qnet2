<?php

use App\Models\User;
use App\Services\Users\UserOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-006: resend-welcome sets a new emailed password, flags the user and revokes every session', function () {
    Notification::fake();
    Sanctum::actingAs(userWithUserAbilities(['update']));
    $target = User::factory()->create();
    $target->createToken('one');
    $target->createToken('two');

    $this->postJson("/api/users/{$target->id}/resend-welcome")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('auth.welcome_resent'))
        ->assertJsonPath('data', null);

    $target->refresh();
    $password = firstAccessSentWelcome($target)->toMail($target)->viewData['password'];

    expect($target->must_set_password)->toBeTrue()
        ->and($target->tokens()->count())->toBe(0)
        ->and(Hash::check('password', $target->password))->toBeFalse()
        ->and(Hash::check($password, $target->password))->toBeTrue()
        ->and($password)->toHaveLength(12);
});

it('AC-007: resend-welcome is 422 for inactive and self targets, changing nothing', function () {
    Notification::fake();
    $actor = userWithUserAbilities(['update']);
    Sanctum::actingAs($actor);

    $inactive = User::factory()->create(['is_active' => false]);

    foreach ([$inactive, $actor] as $target) {
        $token = $target->createToken('api');
        $before = $target->fresh()->password;

        $this->postJson("/api/users/{$target->id}/resend-welcome")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user');

        $fresh = $target->fresh();
        expect($fresh->password)->toBe($before)
            ->and($fresh->must_set_password)->toBeFalse()
            ->and($fresh->tokens()->whereKey($token->accessToken->id)->exists())->toBeTrue();
    }

    Notification::assertNothingSent();
});

it('AC-007: resend-welcome is 403 without the update permission and 404 on an unknown user', function () {
    Notification::fake();
    $target = User::factory()->create();

    Sanctum::actingAs(userWithUserAbilities(['view']));
    $this->postJson("/api/users/{$target->id}/resend-welcome")->assertForbidden();
    expect($target->fresh()->must_set_password)->toBeFalse();

    Sanctum::actingAs(userWithUserAbilities(['update']));
    $this->postJson('/api/users/999999/resend-welcome')->assertNotFound();

    Notification::assertNothingSent();
});

it('AC-007: the service refuses a service account, which the users routes already hide with a 404', function () {
    $service = User::factory()->create(['is_service_account' => true]);
    $before = $service->password;

    expect(fn () => app(UserOnboardingService::class)->resendWelcome($service, User::factory()->create()))
        ->toThrow(ValidationException::class);
    expect($service->fresh()->password)->toBe($before);

    Sanctum::actingAs(userWithUserAbilities(['update']));
    $this->postJson("/api/users/{$service->id}/resend-welcome")->assertNotFound();
});
