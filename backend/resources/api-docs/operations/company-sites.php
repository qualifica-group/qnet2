<?php

return [
    'POST /company-sites' => [
        'summary' => 'Create a company site',
        'description' => 'Creates a site of the company (its own office or legal seat) with name, notes, optional company_id, an optional logo image (multipart), its banks and an optional personal_data profile (type individual or company, identity and tax data, contacts and at most one address). Among the banks at most one is_primary: marking one demotes the others. Answers 201 with the site and the permissions block. Requires company-sites.create.',
    ],
    'GET /company-sites/for-select' => [
        'summary' => 'List company sites for a select',
        'description' => 'Returns a searchable, paginated list of company sites as id, label (the site name) and subtitle (the company name), ordered by name (items + pagination). search filters by name; company_id restricts to the sites of one company; ids[] adds those sites even outside the page; include_total false skips the total count. Requires authentication only.',
    ],
    'GET /company-sites/{companySite}' => [
        'summary' => 'Get a company site',
        'description' => 'Returns the site with its default flag, logo as a data URI, company, profile (identity, tax data, contacts, address), banks and custom fields, plus the permissions block of the caller on it. Requires company-sites.view.',
    ],
    'PUT /company-sites/{companySite}' => [
        'summary' => 'Update a company site',
        'description' => 'Partially updates the site; only submitted fields change. banks, when sent, is the full set: a row with the id of an existing bank of this site is updated, a row without it is created and banks left out are deleted. The logo is changed through its own endpoints. 422 on invalid profile data or a field not editable by the caller. Requires company-sites.update.',
    ],
    'DELETE /company-sites/{companySite}' => [
        'summary' => 'Delete a company site',
        'description' => 'Deletes the company site and answers 204 with no content. Requires company-sites.delete.',
    ],
    'POST /company-sites/{companySite}/logo' => [
        'summary' => 'Upload the logo of a company site',
        'description' => 'Uploads (multipart field logo: a jpeg, png, gif or webp image) the logo of the site, replacing and deleting any previous one. Returns the updated site with the permissions block. Requires company-sites.update.',
    ],
    'DELETE /company-sites/{companySite}/logo' => [
        'summary' => 'Remove the logo of a company site',
        'description' => 'Deletes the logo of the site, if any, and returns the updated site with the permissions block. Requires company-sites.update.',
    ],
    'POST /company-sites/{companySite}/set-default' => [
        'summary' => 'Make a company site the default one',
        'description' => 'Marks this site as the default company site and clears the flag on every other site, so exactly one is the default. No body. Returns the updated site with the permissions block. Requires company-sites.update.',
    ],
];
