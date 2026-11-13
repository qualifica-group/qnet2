<?php

return [
    'GET /states' => [
        'summary' => 'List the states of a country',
        'description' => 'Returns every state (region) of the required country_id (id, localized name, country_id) ordered by name, unpaginated, for the address cascade. 422 when country_id is missing or unknown. Needs only authentication.',
    ],
    'GET /states/for-select' => [
        'summary' => 'List states for a select',
        'description' => 'Returns states as select options (id, localized name as label, country name as subtitle) ordered by name, limited to the configured national country when one is set. search matches the name; offset and limit page the list; ids[] adds the given ids, even outside the national country, to hydrate an existing value. Responds with items and pagination. Needs only authentication.',
    ],
];
