<?php

namespace App\Services\Users;

use App\Models\User;
use App\Notifications\WelcomeUserNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * First-access orchestration (specs 0177, 0355). The system generates the
 * password and sends it in the welcome email; the user must replace it on the
 * first login. Kept outside UserService::create() because that method is
 * shared with the legacy migration, which must never flag users nor send
 * emails.
 */
class UserOnboardingService
{
    /** Readable in an email and compliant with Password::defaults() (min 8). */
    public const int PASSWORD_LENGTH = 12;

    public function generatePassword(): string
    {
        return Str::password(self::PASSWORD_LENGTH, symbols: false);
    }

    /**
     * Flag a just-created user (whose stored password is $password) as pending
     * first access and send the welcome email.
     */
    public function start(User $user, string $password): void
    {
        // Step 1: pending first-access flag (guarded column, forceFill only)
        $user->forceFill(['must_set_password' => true])->save();

        // Step 2: queued welcome email
        $user->notify(new WelcomeUserNotification($password));
    }

    /**
     * Replace the user's password with a new generated one, force the choice of
     * a new password, close every open session and email the new password.
     *
     * @throws ValidationException when the user is inactive, a service account or the actor
     */
    public function resendWelcome(User $user, User $actor): void
    {
        // Step 1: eligibility
        $this->assertEligible($user, $actor);

        // Step 2: new password, flag and session revocation, atomically
        $password = $this->generatePassword();

        DB::transaction(function () use ($user, $password): void {
            $user->forceFill(['password' => $password, 'must_set_password' => true])->save();
            $user->tokens()->delete();
        });

        // Step 3: email after the commit
        $user->notify(new WelcomeUserNotification($password));
    }

    private function assertEligible(User $user, User $actor): void
    {
        if (! $user->is_active || $user->isServiceAccount() || $user->is($actor)) {
            throw ValidationException::withMessages([
                'user' => [__('auth.welcome_not_allowed')],
            ]);
        }
    }
}
