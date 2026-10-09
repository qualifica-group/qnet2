<?php

namespace App\Services;

use App\DataObjects\Auth\LoginResult;
use App\DataObjects\Users\ProfileData;
use App\Models\User;
use App\Services\Users\UserOnboardingService;
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
        $user = $this->authenticate($email, $password);

        $token = $user->createToken($deviceName)->plainTextToken;

        return new LoginResult(user: $user, token: $token);
    }

    /**
     * Check the credentials of a human user able to sign in. Shared by the app
     * login and by client-login (spec 0210). The app login tells an inactive
     * account apart; client-login does not ($revealInactive = false), so it
     * answers every failure with the same message.
     *
     * @throws ValidationException
     */
    public function authenticate(string $email, string $password, bool $revealInactive = true): User
    {
        $user = User::where('email', $email)->first();

        // A technical user (API client) never signs in: same message as a wrong
        // password, so the caller cannot tell it exists.
        if (! $user || ! Hash::check($password, $user->password) || $user->isServiceAccount()) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        // An inactive account keeps its record but may not sign in. Checked only
        // after the credentials pass so an unauthenticated caller can never probe
        // which accounts are inactive.
        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => [__($revealInactive ? 'auth.inactive' : 'auth.failed')],
            ]);
        }

        return $user;
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

        // A client key is rotated only from the administration (spec 0210).
        abort_if($user->isServiceAccount() && $user->currentApiClientId() !== null, 403, __('An API client key cannot be refreshed.'));

        $deviceName = $current->name;
        $apiClientId = $user->currentApiClientId();
        $expiresAt = $current->expires_at;
        $current->delete();

        // A client-login token keeps its client and its expiry, so a refresh
        // never leaves the client's rate limit nor its validity rule (R-2).
        $newToken = $user->createToken($deviceName, ['*'], $apiClientId === null ? null : $expiresAt);

        if ($apiClientId !== null) {
            $newToken->accessToken->forceFill(['api_client_id' => $apiClientId])->save();
        }

        return $newToken->plainTextToken;
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
     * @param  array<string, mixed>  $attributes  whitelisted account fields (locale, ui_scale, date_format, time_format, color_preset)
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
            'must_set_password' => false,
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
        // A technical user has no mailbox: report the generic success without sending.
        if ($this->isServiceAccountEmail($email)) {
            return Password::RESET_LINK_SENT;
        }

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
        if ($this->isServiceAccountEmail($data['email'])) {
            return Password::INVALID_TOKEN;
        }

        return Password::reset($data, function (User $user, string $password): void {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
                'must_set_password' => false,
            ])->save();

            $user->tokens()->delete();

            event(new PasswordReset($user));
        });
    }

    /**
     * Complete the first access from an invite token (spec 0177): same effects
     * as a reset (password, revoked sessions) plus clearing the pending flag.
     * The token lives in the dedicated `users_setup` broker table.
     *
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $data
     */
    public function setPassword(array $data): string
    {
        if ($this->isServiceAccountEmail($data['email'])) {
            return Password::INVALID_TOKEN;
        }

        return Password::broker(UserOnboardingService::BROKER)->reset($data, function (User $user, string $password): void {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
                'must_set_password' => false,
            ])->save();

            $user->tokens()->delete();

            event(new PasswordReset($user));
        });
    }

    private function isServiceAccountEmail(string $email): bool
    {
        return User::query()->where('email', $email)->where('is_service_account', true)->exists();
    }
}
