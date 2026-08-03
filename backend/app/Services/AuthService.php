<?php

namespace App\Services;

use App\DataObjects\Auth\LoginResult;
use App\DataObjects\Users\ProfileData;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(private readonly ProfileWriter $profileWriter) {}

    /**
     * Verify the credentials and issue a new access token.
     *
     * @throws ValidationException
     */
    public function login(string $email, string $password, string $deviceName): LoginResult
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        // An inactive account keeps its record but may not sign in. Checked only
        // after the credentials pass so an unauthenticated caller can never probe
        // which accounts are inactive.
        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => [__('auth.inactive')],
            ]);
        }

        $token = $user->createToken($deviceName)->plainTextToken;

        return new LoginResult(user: $user, token: $token);
    }

    /**
     * Revoca il token attualmente in uso.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    /**
     * Ruota il token: revoca quello corrente ed emette un nuovo token
     * mantenendo lo stesso device name.
     */
    public function refresh(User $user): string
    {
        $current = $user->currentAccessToken();
        $deviceName = $current->name;
        $current->delete();

        return $user->createToken($deviceName)->plainTextToken;
    }

    /**
     * Update the authenticated user's own account fields and, when submitted,
     * their personal-data profile (card + contacts + addresses) — ADR 0013.
     *
     * The account fields (locale only) and the nested profile are written in a
     * single transaction so a failure leaves no half-applied state. The email is
     * READ-ONLY on self-service (registration email) and is never written from
     * this path. The profile is persisted through the shared ProfileWriter (the
     * same path the Users module uses), which also derives `users.name` from the
     * card — so on self-service the name is NOT client-supplied. The owner is
     * always $user by construction; any personable_* in the input is irrelevant.
     * A null $profile leaves the card untouched.
     *
     * `module_open_preferences` (spec 0042) is written OUTSIDE `$attributes` on
     * purpose: the column is guarded (not in `User::$fillable`, AC-008), so it
     * is set via `forceFill()` — same pattern as the guarded `name`/`password`
     * writes above — rather than mass assignment. A null value here leaves the
     * stored preference untouched (client omitted the key).
     *
     * @param  array<string, mixed>  $attributes  whitelisted account fields (locale, ui_scale, date_format, time_format)
     * @param  array{mode: string, overrides: array<string, string>}|null  $moduleOpenPreferences
     */
    public function updateProfile(User $user, array $attributes, ?ProfileData $profile = null, ?array $moduleOpenPreferences = null): User
    {
        return DB::transaction(function () use ($user, $attributes, $profile, $moduleOpenPreferences): User {
            if ($attributes !== []) {
                $user->update($attributes);
            }

            if ($moduleOpenPreferences !== null) {
                $user->forceFill(['module_open_preferences' => $moduleOpenPreferences])->save();
            }

            $this->profileWriter->write($user, $profile);

            return $user->load(['personalData.contacts', 'personalData.addresses']);
        });
    }

    /**
     * Change the user's password, then revoke every other access token while
     * keeping the one used for the current request so the session stays valid.
     */
    public function changePassword(User $user, string $newPassword): void
    {
        $user->forceFill([
            'password' => Hash::make($newPassword),
        ])->save();

        $user->tokens()
            ->where('id', '!=', $user->currentAccessToken()->id)
            ->delete();
    }

    /**
     * Send a password reset link to the given email.
     *
     * Returns the password broker status. The caller is responsible for keeping
     * the HTTP response generic to avoid leaking whether the account exists.
     */
    public function sendPasswordResetLink(string $email): string
    {
        return Password::sendResetLink(['email' => $email]);
    }

    /**
     * Reset the user's password from a valid token, then revoke every existing
     * access token so any other active session is invalidated.
     *
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $data
     */
    public function resetPassword(array $data): string
    {
        return Password::reset($data, function (User $user, string $password): void {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            $user->tokens()->delete();

            event(new PasswordReset($user));
        });
    }
}
