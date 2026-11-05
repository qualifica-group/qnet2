<?php

use App\Models\User;
use App\Notifications\WelcomeUserNotification;
use App\Services\Users\UserOnboardingService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-001: creating a user sends a generated 12 character password that works, ignoring the one submitted', function () {
    Notification::fake();
    Sanctum::actingAs(userWithUserAbilities(['create']));

    $this->postJson('/api/users', firstAccessCreatePayload([
        'password' => 'Client-Chosen-1!',
        'password_confirmation' => 'Client-Chosen-1!',
    ]))->assertCreated()
        ->assertJsonPath('data.must_set_password', true);

    $user = User::where('email', 'new.person@example.com')->firstOrFail();
    expect($user->must_set_password)->toBeTrue();

    $notification = firstAccessSentWelcome($user);
    expect($notification)->not->toBeNull();

    $viewData = $notification->toMail($user)->viewData;
    expect($viewData['password'])->toHaveLength(12)
        ->and($viewData['password'])->not->toBe('Client-Chosen-1!')
        ->and($viewData['url'])->toEndWith('/login');

    $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'Client-Chosen-1!', 'device_name' => 'test'])
        ->assertStatus(422);
    $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => $viewData['password'], 'device_name' => 'test'])
        ->assertOk();
});

it('AC-002: the notification is encrypted in the queue and a failed dispatch logs no password', function () {
    expect(new WelcomeUserNotification('Ab3dEf6hJk9m'))->toBeInstanceOf(ShouldBeEncrypted::class);

    $password = 'Fixed12Pass34';
    $this->partialMock(UserOnboardingService::class, function ($mock) use ($password) {
        $mock->shouldReceive('generatePassword')->andReturn($password);
        $mock->shouldReceive('start')->once()->andThrow(new RuntimeException("queue down {$password}"));
    });
    Log::spy();
    Sanctum::actingAs(userWithUserAbilities(['create']));

    $this->postJson('/api/users', firstAccessCreatePayload())->assertCreated();

    $this->assertDatabaseHas('users', ['email' => 'new.person@example.com']);
    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn (string $message, array $context) => ! str_contains($message.json_encode($context), $password),
    );
});
