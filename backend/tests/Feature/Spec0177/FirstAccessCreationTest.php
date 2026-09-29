<?php

use App\Jobs\RunMigrationJob;
use App\Models\MigrationRun;
use App\Models\User;
use App\Notifications\WelcomeUserNotification;
use App\Services\MigrationService;
use App\Services\Users\UserOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function firstAccessCreatePayload(array $overrides = []): array
{
    return array_merge([
        'email' => 'new.person@example.com',
        'locale' => 'it',
        'personal_data' => individualProfile(),
    ], $overrides);
}

it('AC-001: creating without password flags the user and sends the invite email', function () {
    Notification::fake();
    Sanctum::actingAs(userWithUserAbilities(['create']));

    $this->postJson('/api/users', firstAccessCreatePayload())
        ->assertCreated()
        ->assertJsonPath('data.must_set_password', true);

    $user = User::where('email', 'new.person@example.com')->firstOrFail();
    expect($user->must_set_password)->toBeTrue();

    $notification = firstAccessSentWelcome($user);
    expect($notification)->not->toBeNull()
        ->and($notification->isInvite())->toBeTrue();

    $url = firstAccessWelcomeUrl($notification, $user);
    expect($url)->toContain('/set-password?token=')
        ->and($url)->toContain(urlencode('new.person@example.com'));
});

it('AC-002: creating with a temporary password sends the login variant without the password', function () {
    Notification::fake();
    Sanctum::actingAs(userWithUserAbilities(['create']));
    $password = 'Str0ng-P4ssw0rd!';

    $this->postJson('/api/users', firstAccessCreatePayload([
        'password' => $password,
        'password_confirmation' => $password,
    ]))->assertCreated()
        ->assertJsonPath('data.must_set_password', true);

    $user = User::where('email', 'new.person@example.com')->firstOrFail();
    $this->postJson('/api/auth/login', ['email' => 'new.person@example.com', 'password' => $password, 'device_name' => 'test'])
        ->assertOk()
        ->assertJsonPath('success', true);

    $notification = firstAccessSentWelcome($user);
    expect($notification->isInvite())->toBeFalse();

    $mail = $notification->toMail($user);
    expect(firstAccessWelcomeUrl($notification, $user))->toEndWith('/login')
        ->and((string) $mail->render())->not->toContain($password)
        ->and(json_encode($mail->viewData))->not->toContain($password);
});

it('AC-003: a mismatched password confirmation is a 422 on password and sends nothing', function () {
    Notification::fake();
    Sanctum::actingAs(userWithUserAbilities(['create']));

    $this->postJson('/api/users', firstAccessCreatePayload([
        'password' => 'Str0ng-P4ssw0rd!',
        'password_confirmation' => 'different',
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    Notification::assertNothingSent();
    $this->assertDatabaseMissing('users', ['email' => 'new.person@example.com']);
});

it('AC-013: users from the factory and from the legacy source are never flagged nor mailed', function () {
    Notification::fake();

    $factoryUser = User::factory()->create();
    expect($factoryUser->fresh()->must_set_password)->toBeFalse();

    seedMigrationsConfig();
    $actor = User::factory()->create();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'users']);
    Http::fake([
        fakeMigrationsBaseUrl().'/users*' => Http::response([
            'items' => [[
                'id' => 501,
                'email' => 'legacy@example.test',
                'password' => Hash::make('legacy-secret'),
                'first_name' => 'Legacy',
                'last_name' => 'Person',
                'is_active' => true,
                'roles' => [],
            ]],
            'pagination' => ['total' => 1, 'offset' => 0, 'limit' => 50, 'total_pages' => 1],
        ]),
    ]);

    (new RunMigrationJob($run->id))->handle(app(MigrationService::class));

    $legacy = User::where('email', 'legacy@example.test')->firstOrFail();
    expect($legacy->must_set_password)->toBeFalse();
    Notification::assertNothingSent();
});

it('AC-014: must_set_password is exposed as a boolean by /auth/me and /users/{user}', function () {
    $actor = userWithUserAbilities(['view']);
    $token = $actor->createToken('api')->plainTextToken;
    $target = firstAccessPendingUser();

    $this->withToken($token)->getJson('/api/auth/me')
        ->assertOk()->assertJsonPath('data.must_set_password', false);

    $this->withToken($token)->getJson("/api/users/{$target->id}")
        ->assertOk()->assertJsonPath('data.must_set_password', true);
});

it('AC-015: a failing notification dispatch still returns 201 and the invite can be resent', function () {
    $this->partialMock(UserOnboardingService::class, function ($mock) {
        $mock->shouldReceive('start')->once()->andThrow(new RuntimeException('queue down'));
    });
    Sanctum::actingAs(userWithUserAbilities(['create', 'update']));

    $response = $this->postJson('/api/users', firstAccessCreatePayload())->assertCreated();
    $user = User::findOrFail($response->json('data.id'));

    // start() failed before flagging the user; simulate the flag an admin-created user carries.
    Notification::fake();
    $user->forceFill(['must_set_password' => true])->save();

    $this->postJson("/api/users/{$user->id}/resend-welcome")->assertOk();

    Notification::assertSentTo($user, WelcomeUserNotification::class, fn ($n) => $n->isInvite());
});
