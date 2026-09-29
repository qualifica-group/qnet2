<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'failed' => 'These credentials do not match our records.',
    'inactive' => 'This account is inactive. Please contact an administrator.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
    'must_set_password' => 'You must choose a new password before continuing.',
    'welcome_resent' => 'Welcome email sent.',
    'first_access_completed' => 'The user has already completed the first access.',
    'logged_out' => 'Logged out successfully.',
    'profile_updated' => 'Profile updated successfully.',
    'password_updated' => 'Password updated successfully.',
    'avatar_updated' => 'Avatar updated successfully.',
    'avatar_removed' => 'Avatar removed successfully.',

    // "Login as customer" impersonation (spec 0050).
    'impersonation_self' => 'You cannot impersonate yourself.',
    'impersonation_inactive' => 'This account is inactive and cannot be impersonated.',
    'impersonation_nesting' => 'You are already impersonating a user; stop the current session first.',
    'impersonation_escalation' => 'Only a super-admin can impersonate a super-admin.',
    'impersonation_not_active' => 'The current session is not an impersonation session.',
    'impersonation_original_inactive' => 'Your account is no longer active; you have been signed out of the impersonation session.',
    'impersonation_started' => 'Impersonation started.',
    'impersonation_stopped' => 'Impersonation stopped.',

];
