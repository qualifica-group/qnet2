<?php

return [
    'POST /operational-sites' => [
        'summary' => 'Create an operational site',
        'description' => 'Creates an operational site (a physical location of the company, used to scope leads, requests, staff and stock). A site has no name of its own: it is identified by its address, sent flat (line1 and city_id required; postal_code, province_id, state_id, country_id optional), plus an optional alias and is_active. Returns 201 with the site and the caller\'s permissions block. Requires operational-sites.create.',
    ],
    'GET /operational-sites/for-select' => [
        'summary' => 'List operational sites for a select',
        'description' => 'Lightweight option list of active operational sites (id, label = address line and city, subtitle = postal code), ordered by address. search matches the address line or city; business_function_id restricts to the sites linked to that business function; offset and limit (max 100) paginate; ids adds specific sites, even inactive, to show a form\'s current value. Response is items plus pagination. Needs only authentication.',
    ],
    'GET /operational-sites/{operationalSite}' => [
        'summary' => 'Get an operational site',
        'description' => 'Returns the site with its alias, address (line, postal code, city, province, region and country with ids and names) and active flag, plus the caller\'s permissions block. Requires operational-sites.view.',
    ],
    'PUT /operational-sites/{operationalSite}' => [
        'summary' => 'Update an operational site',
        'description' => 'Partial update (PATCH accepted): only the fields sent change, the address fields being merged onto the current address. Same fields as creation; a change to a field the caller may not edit is a 422. Returns the site with its permissions block. Requires operational-sites.update.',
    ],
    'DELETE /operational-sites/{operationalSite}' => [
        'summary' => 'Delete an operational site',
        'description' => 'Deletes the site and its address; returns 204. 409 when the site is still in use (business functions, campaigns, company closures, employment profiles, leads, opportunities, offers, projects, purchase documents, stock), with a message listing the uses and their counts. Requires operational-sites.delete.',
    ],
];
