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

// REQUIREMENT CHANGED (spec 0355): AC-001..AC-003 (invite / temporary-password variants,
// optional password field) are superseded by tests/Feature/Spec0355/WelcomePasswordCreationTest.php.

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

// REQUIREMENT CHANGED (spec 0355): the resend is no longer limited to pending users
// and the welcome email carries a generated password instead of an invite token.
it('AC-015: a failing notification dispatch still returns 201 and the welcome email can be resent', function () {
    $this->partialMock(UserOnboardingService::class, function ($mock) {
        $mock->shouldReceive('start')->once()->andThrow(new RuntimeException('queue down'));
    });
    Sanctum::actingAs(userWithUserAbilities(['create', 'update']));

    $response = $this->postJson('/api/users', firstAccessCreatePayload())->assertCreated();
    $user = User::findOrFail($response->json('data.id'));

    Notification::fake();
    $this->postJson("/api/users/{$user->id}/resend-welcome")->assertOk();

    Notification::assertSentTo($user, WelcomeUserNotification::class);
});
