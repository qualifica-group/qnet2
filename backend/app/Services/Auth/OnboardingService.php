<?php

namespace App\Services\Auth;

use App\Models\User;

/**
 * Records that a user went through the welcome tour (spec 0368). The seen
 * columns are guarded, so they are written only here via forceFill().
 * The impersonation refusal (D-8) lives in UpdateOnboardingRequest::authorize(),
 * which must run before validation so the 403 wins over a 422.
 */
class OnboardingService
{
    /**
     * Idempotent: repeating the call keeps the current version, refreshes the timestamp.
     */
    public function markSeen(User $user): User
    {
        // Step 1: stamp the current tour version.
        $user->forceFill([
            'onboarding_seen_version' => config('auth.onboarding_version'),
            'onboarding_seen_at' => now(),
        ])->save();

        return $user;
    }
}
