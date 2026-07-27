<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Development seed credentials
    |--------------------------------------------------------------------------
    |
    | Plain-text password assigned to every account created by the development
    | seeders (the privileged demo user and the generated fixtures). It only
    | exists to make local/demo accounts loginable; it is never a production
    | secret. Override it per environment via SEED_PASSWORD when needed.
    |
    */

    'password' => env('SEED_PASSWORD', 'password'),

    /*
    |--------------------------------------------------------------------------
    | Tester account credentials
    |--------------------------------------------------------------------------
    |
    | Shared plain-text password of the named tester accounts (TestUsersSeeder).
    | Kept separate from the value above so the client-facing testers can be
    | handed one credential without moving the demo/fixture accounts onto it.
    | Same nature: a convenience credential for non-production environments.
    |
    */

    'test_users_password' => env('TEST_USERS_SEED_PASSWORD', 'Qualifica2026!'),

];
