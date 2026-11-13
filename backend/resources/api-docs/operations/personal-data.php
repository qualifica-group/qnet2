<?php

return [
    'GET /personal-data' => [
        'summary' => 'Get the personal data card of an owner',
        'description' => 'Returns the single personal data card held by an owner, identified by the query parameters personable_type (user) and personable_id, with its contacts, addresses, birth city and residence city; data is null when the owner has no card yet. 422 when the owner does not exist. Requires personal_data.viewAny.',
    ],
    'POST /personal-data' => [
        'summary' => 'Create the personal data card of an owner',
        'description' => 'Creates the personal data card of an owner (personable_type user plus personable_id). type is individual (first_name and last_name required) or company (company_name required); tax_code and vat_number are format-checked and must not already belong to another user, registry or referent. 422 when the owner already has a card. Answers 201 with the card. Requires personal_data.create.',
    ],
    'GET /personal-data/{personalData}' => [
        'summary' => 'Get a personal data card',
        'description' => 'Returns the personal data card with its contacts, addresses, birth city and residence city. Requires personal_data.view.',
    ],
    'PUT /personal-data/{personalData}' => [
        'summary' => 'Update a personal data card',
        'description' => 'Replaces the card fields with the same rules as creation (type, names, tax_code, vat_number, sdi_code, birth data, residence city, gender); the owner cannot change. 422 when tax_code or vat_number already belongs to another user, registry or referent. Returns the updated card with contacts and addresses. Requires personal_data.update.',
    ],
    'DELETE /personal-data/{personalData}' => [
        'summary' => 'Delete a personal data card',
        'description' => 'Deletes the card together with its contacts and addresses. Answers 204 with no content. Requires personal_data.delete.',
    ],
];
