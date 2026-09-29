<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\WelcomeUserNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

if (! function_exists('firstAccessPendingUser')) {
    /** A user flagged as pending first access, as UserOnboardingService::start() leaves it. */
    function firstAccessPendingUser(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['must_set_password' => true])->save();

        return $user;
    }
}

if (! function_exists('firstAccessInviteToken')) {
    function firstAccessInviteToken(User $user): string
    {
        return Password::broker('users_setup')->createToken($user);
    }
}

if (! function_exists('firstAccessSentWelcome')) {
    /**
     * The WelcomeUserNotification sent to $user (Notification::fake() must be active),
     * or null when none was sent.
     */
    function firstAccessSentWelcome(User $user): ?WelcomeUserNotification
    {
        $sent = Notification::sent($user, WelcomeUserNotification::class);

        return $sent->last();
    }
}

if (! function_exists('firstAccessWelcomeUrl')) {
    function firstAccessWelcomeUrl(WelcomeUserNotification $notification, User $user): string
    {
        return $notification->toMail($user)->viewData['url'];
    }
}
