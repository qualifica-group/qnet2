<?php

return [
    'GET /countries' => [
        'summary' => 'List all countries',
        'description' => 'Returns every country (id, localized name, ISO 3166-1 alpha-2 code) ordered by name, unpaginated, for the first step of the country, state, province, city address cascade. Needs only authentication.',
    ],
];
