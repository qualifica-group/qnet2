<?php

namespace App\Services\Auth;

use App\DataObjects\Auth\LoginResult;
use App\Models\User;
use App\Services\RoleAssignmentGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * "Login as customer" impersonation (spec 0050, D-1): starting a session
 * issues a NEW Sanctum token headed to the TARGET user, tagged with
 * `personal_access_tokens.impersonated_by` = the original actor's id. Every
 * Policy/Gate/query downstream then runs with $request->user() = target, so
 * the target's own permissions and writes apply with zero changes to
 * existing authorization code (blast radius minimum).
 *
 * D-2 (no nesting) and D-3 (no self, target active, no escalation) are
 * enforced HERE unconditionally, not only in UserPolicy::impersonate():
 * Gate::before (AppServiceProvider) grants a super-admin actor every ability
 * and never reaches the Policy, so self/inactive/nesting can only be
 * guaranteed for every actor — super-admin included — at this layer. Per the
 * frozen data contract, self/inactive/nesting are business-rule violations
 * (422); only the super-admin escalation guard is a genuine authorization
 * denial (403), repeated here as defense in depth.
 */
class ImpersonationService
{
    private const string TOKEN_NAME = 'impersonation';

    /**
     * @throws ValidationException self-impersonation, inactive target, or already-nested session
     * @throws AuthorizationException actor is not a super-admin and the target is
     */
    public function start(User $actor, User $target): LoginResult
    {
        // Step 1: invariants, repeated unconditionally (see class docblock).
        $this->assertNotThroughApiClient($actor);
        $this->assertNotNesting($actor);
        $this->assertNotSelf($actor, $target);
        $this->assertTargetActive($target);
        $this->assertNoEscalation($actor, $target);

        // Step 2: issue a dedicated token for the target, tagged with the actor.
        $issued = $target->createToken(self::TOKEN_NAME);
        $issued->accessToken->forceFill(['impersonated_by' => $actor->id])->save();

        // Step 3: audit (D-6) — causer is the ORIGINAL actor, subject the target.
        $this->log('impersonation.started', $actor, $target);

        return new LoginResult(user: $target, token: $issued->plainTextToken);
    }

    /**
     * Impersonation tokens carry no API client binding, so an API client token
     * must never mint one (spec 0210): it would escape the client's rate limit
     * and validity rules.
     */
    private function assertNotThroughApiClient(User $user): void
    {
        abort_if($user->currentApiClientId() !== null, 403, __('Impersonation is not available through an API client.'));
    }

    /**
     * The original actor's row is fetched with `find()`, not `findOrFail()`:
     * the FK is `nullOnDelete` (impersonated_by -> users.id), so a deleted
     * original actor already nulls the column and is caught by the check
     * above — `find()` returning null here is only a defensive fallback
     * (e.g. FK enforcement disabled on the connection), kept on the SAME 403
     * branch as an inactive original rather than risking a stray 404 outside
     * the frozen contract. Either way the impersonated user is never stuck:
     * a plain logout (AuthService::logout(), which only ever touches
     * currentAccessToken()) still works regardless of `impersonated_by`.
     *
     * @throws AuthorizationException the current token is not an impersonation session, or the original actor is gone/inactive
     */
    public function stop(User $current): LoginResult
    {
        $this->assertNotThroughApiClient($current);

        $currentToken = $this->accessTokenOf($current);

        if ($currentToken === null || $currentToken->impersonated_by === null) {
            throw new AuthorizationException(__('auth.impersonation_not_active'));
        }

        $original = User::find($currentToken->impersonated_by);

        if ($original === null || ! $original->is_active) {
            // The return identity is gone or no longer authorized to hold a
            // session (mirrors the login-time is_active gate, AuthService::
            // login()): re-entry must not hand back a live token. The
            // impersonation token is revoked regardless, so the caller is
            // never left stranded inside it — the client falls back to the
            // login screen off this same 403.
            $currentToken->delete();

            throw new AuthorizationException(__('auth.impersonation_original_inactive'));
        }

        // Step 1: issue a fresh token for the original actor (D-4 — their
        // ORIGINAL token, untouched, stays valid: re-entry never depends on
        // the client having kept it around).
        $reissued = $original->createToken(self::TOKEN_NAME);

        // Step 2: audit (D-6) while the impersonation session is still live,
        // so causer/subject read off it before it is revoked below.
        $this->log('impersonation.stopped', $original, $current);

        // Step 3: revoke the impersonation token.
        $currentToken->delete();

        return new LoginResult(user: $original, token: $reissued->plainTextToken);
    }

    /**
     * The original actor behind the CURRENT token, or null on a normal
     * (non-impersonation) session — feeds GET /auth/impersonation (D-5).
     */
    public function impersonatorFor(User $current): ?User
    {
        $token = $this->accessTokenOf($current);

        if ($token === null || $token->impersonated_by === null) {
            return null;
        }

        return User::find($token->impersonated_by);
    }

    private function accessTokenOf(User $user): ?PersonalAccessToken
    {
        $token = $user->currentAccessToken();

        return $token instanceof PersonalAccessToken ? $token : null;
    }

    /**
     * @throws ValidationException
     */
    private function assertNotNesting(User $actor): void
    {
        $token = $this->accessTokenOf($actor);

        if ($token !== null && $token->impersonated_by !== null) {
            throw ValidationException::withMessages([
                'user' => [__('auth.impersonation_nesting')],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertNotSelf(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw ValidationException::withMessages([
                'user' => [__('auth.impersonation_self')],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertTargetActive(User $target): void
    {
        if (! $target->is_active) {
            throw ValidationException::withMessages([
                'user' => [__('auth.impersonation_inactive')],
            ]);
        }
    }

    /**
     * @throws AuthorizationException
     */
    private function assertNoEscalation(User $actor, User $target): void
    {
        if ($target->hasRole(RoleAssignmentGuard::PRIVILEGED_ROLE) && ! $actor->hasRole(RoleAssignmentGuard::PRIVILEGED_ROLE)) {
            throw new AuthorizationException(__('auth.impersonation_escalation'));
        }
    }

    /**
     * Explicit activity entry (D-6): causer is always the ORIGINAL actor,
     * subject the TARGET user, on both start and stop.
     */
    private function log(string $description, User $actor, User $target): void
    {
        activity($target->getTable())
            ->performedOn($target)
            ->causedBy($actor)
            ->log($description);
    }
}
