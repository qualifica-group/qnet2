<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Development seed credentials
    |--------------------------------------------------------------------------
    |
    | Shared plain-text password assigned to every account created by the
    | seeders: the demo user and fixtures (DemoUserSeeder, DemoUsersSeeder),
    | the named testers (TestUsersSeeder) and the client's operators
    | (QualificaOperatorSeeder). It only exists to make seeded accounts
    | loginable; it is never a production secret. Override it via SEED_PASSWORD.
    |
    */

    'password' => env('SEED_PASSWORD', 'Qualifica2026!'),

    /*
    |--------------------------------------------------------------------------
    | Forced password change on seeded accounts (spec 0177)
    |--------------------------------------------------------------------------
    |
    | When true, every account that receives the shared password above is
    | flagged `must_set_password`: its owner must choose a new password at
    | first access. User directive 2026-09-29: on in production and local, off
    | in staging, where the shared credential stays usable. Defaults to off only
    | for APP_ENV=staging; override via SEED_FORCE_PASSWORD_CHANGE.
    |
    */

    'force_password_change' => (bool) env('SEED_FORCE_PASSWORD_CHANGE', env('APP_ENV') !== 'staging'),

];
