<?php

namespace App\Services\Users;

use App\Models\User;
use App\Notifications\WelcomeUserNotification;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * First-access orchestration (spec 0177). Kept outside UserService::create()
 * because that method is shared with the legacy migration, which must never
 * flag users nor send emails.
 */
class UserOnboardingService
{
    /** Broker with its own token table: invite and reset tokens never mix. */
    public const string BROKER = 'users_setup';

    /**
     * Flag the user as pending first access and send the welcome email: the
     * invite variant (token link) or, when the admin chose a password, the
     * temporary-password variant (login link, no password in the content).
     */
    public function start(User $user, bool $temporaryPassword): void
    {
        // Step 1: pending first-access flag (guarded column, forceFill only)
        $user->forceFill(['must_set_password' => true])->save();

        // Step 2: queued welcome email
        $user->notify(new WelcomeUserNotification($temporaryPassword ? null : $this->issueToken($user)));
    }

    /**
     * Send a fresh invite (always the invite variant); issuing the token
     * replaces any previous one.
     *
     * @throws ValidationException when the user already completed first access
     */
    public function resendWelcome(User $user): void
    {
        if (! $user->must_set_password) {
            throw ValidationException::withMessages([
                'user' => [__('auth.first_access_completed')],
            ]);
        }

        $user->notify(new WelcomeUserNotification($this->issueToken($user)));
    }

    private function issueToken(User $user): string
    {
        return Password::broker(self::BROKER)->createToken($user);
    }
}
