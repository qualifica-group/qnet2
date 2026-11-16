<?php

return [
    'POST /addresses' => [
        'summary' => 'Create an address on a personal-data card',
        'description' => 'Adds a postal address to a personal-data card: addressable_type must be personal_data and addressable_id the card id (the card of a user, registry, referent or company site). Only line1 is required; city, province, state and country ids must exist; site_type is legal_seat, delivery, billing or operational_site. The first address of a card, or one sent with is_primary true, becomes the only primary one. Returns 201 with the address and its place names. Requires update rights on the card owner and its editable addresses section.',
    ],
    'GET /addresses/{address}' => [
        'summary' => 'Get an address',
        'description' => 'Returns the address with its city, province, state and country names. Allowed when the user can view the record that owns the card and its addresses section is visible.',
    ],
    'PUT /addresses/{address}' => [
        'summary' => 'Update an address',
        'description' => 'Replaces the address fields (same rules as creation); the owning card cannot be changed. Setting is_primary true demotes the other addresses of the card. Returns the updated address. Requires update rights on the card owner and its editable addresses section.',
    ],
    'DELETE /addresses/{address}' => [
        'summary' => 'Delete an address',
        'description' => 'Deletes the address and answers 204 with no content. Requires update rights on the card owner and its editable addresses section.',
    ],
];
