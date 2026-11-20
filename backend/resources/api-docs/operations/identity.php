<?php

return [
    'POST /identity/duplicate-check' => [
        'summary' => 'Check for existing holders of an identity',
        'description' => 'Non-blocking duplicate check run while filling a registry or referent form: given a tax_code, a vat_number and/or email/phone contacts (at least one is required, 422 otherwise), returns up to 5 existing users, registries or referents holding the same value. Each match carries owner_type (user, registry, referent), owner_id, name, the matched channels and the registries it leads to. Fiscal codes are matched across both tax code and VAT columns. Nothing is written. Requires registries.create or referents.create.',
    ],
];
