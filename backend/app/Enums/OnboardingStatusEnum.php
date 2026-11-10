<?php

namespace App\Enums;

/**
 * Welcome tour state of a user (spec 0368 D-6), computed from the seen version
 * against `config('auth.onboarding_version')`. Exposed only in the authenticated
 * user payload (`/auth/me`).
 */
enum OnboardingStatusEnum: string
{
    /** Never seen the tour: it is mandatory. */
    case Required = 'required';

    /** Seen an older version of the tour: it restarts once, skippable. */
    case Outdated = 'outdated';

    /** Seen the current version. */
    case Completed = 'completed';
}
