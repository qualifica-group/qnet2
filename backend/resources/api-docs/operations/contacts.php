<?php

return [
    'POST /contacts' => [
        'summary' => 'Add a contact to a personal-data card',
        'description' => 'Creates a contact channel on a personal-data card: contactable_type must be personal_data and contactable_id the card id (the card of a user, registry, referent or company site). type is one of phone, fax, email, pec, website, and value is validated and normalized for that type. Setting is_primary demotes any other primary contact of the same type on the card. 422 when a phone number is already held by another identity card. Allowed when the caller can update the record owning the card and edit its contacts section; there is no contacts permission of its own. Returns 201 with the contact.',
    ],
    'GET /contacts/{contact}' => [
        'summary' => 'Get a contact',
        'description' => 'Returns a single contact channel (type, label, value, primary and work flags, owning card). Allowed when the caller can view the record owning the card and its contacts section.',
    ],
    'PUT /contacts/{contact}' => [
        'summary' => 'Update a contact',
        'description' => 'Full replacement of the contact\'s type, label, value, is_primary and is_work; the owning card cannot be changed. Making it primary demotes any other primary contact of the same type on the card. 422 when a new phone number is already held by another identity card. Allowed when the caller can update the record owning the card and edit its contacts section.',
    ],
    'DELETE /contacts/{contact}' => [
        'summary' => 'Delete a contact',
        'description' => 'Deletes the contact channel. Allowed when the caller can update the record owning the card and edit its contacts section. Returns 204 no content.',
    ],
];
