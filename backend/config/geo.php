<?php

return [

    /*
    |--------------------------------------------------------------------------
    | National mode — default country
    |--------------------------------------------------------------------------
    |
    | ISO 3166-1 alpha-2 code of the country the UI preselects when it renders
    | an EMPTY geo cascade (e.g. "IT"). This is the switch between the two
    | modes of the app:
    |
    |   - set (e.g. "IT")  → national mode: the country field opens already on
    |                        that country, still changeable by the user.
    |   - empty / unset    → international mode: nothing is preselected (the
    |                        behaviour the app had before this setting existed).
    |
    | It is a CODE, never a database id: the client resolves it against the
    | `countries` table, so the value stays valid across environments whose geo
    | reference data was seeded with different ids.
    |
    | Exposed to the frontend by the public bootstrap GET /api/config
    | (ConfigService::localization) — a static country code, no user-, tenant-
    | or permission-scoped data.
    |
    */

    'default_country_iso2' => env('DEFAULT_COUNTRY_ISO2'),

];
