<?php

use App\Models\PersonalData;
use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Polymorphic owner allowlists
    |--------------------------------------------------------------------------
    |
    | Each list maps a public, stable alias the client may send (e.g.
    | personable_type=user) to the concrete owning model class. This is the
    | security boundary for the polymorphic relations of the PersonalData
    | module: only models listed here can be targeted when creating a card,
    | a contact or an address, so a request can never attach an entity to an
    | arbitrary class.
    |
    | The stable alias is persisted in the morph column: a global morph map is
    | enforced (see AppServiceProvider::boot), so getMorphClass() returns the
    | alias rather than the FQCN. The same alias is also the wire format.
    |
    | Add a new owner type by listing it here AND using the matching trait
    | (HasPersonalData / HasContacts / HasAddresses) on the model — no schema
    | change is required.
    |
    */

    'personable_types' => [
        'user' => User::class,
    ],

    'contactable_types' => [
        'personal_data' => PersonalData::class,
    ],

    'addressable_types' => [
        'personal_data' => PersonalData::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Card owner -> authorization resource
    |--------------------------------------------------------------------------
    |
    | Morph alias of a card's owner (`personable`) mapped to the authorization
    | resource of config/authorization.php that governs it. The contacts and
    | addresses of a card are authorized through that resource: its policy and
    | its `personal_data.contacts` / `personal_data.addresses` field rows
    | (spec 0328, PersonalDataChildAuthorization). An owner type missing here
    | is never authorized.
    |
    */

    'owner_resources' => [
        'user' => 'users',
        'registry' => 'registries',
        'referent' => 'referents',
        'company_site' => 'company-sites',
    ],

];
