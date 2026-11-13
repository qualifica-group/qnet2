<?php

return [
    'GET /cities' => [
        'summary' => 'List cities of a province, a state or by name',
        'description' => 'Returns cities ordered by name, 50 at a time: those of province_id when given, otherwise those of state_id; without either, search is required and the lookup runs on the city name across the configured national country. search filters by name and also matches the localized display name; offset skips the rows already loaded. Each item has id, localized name and its country, state and province ids. 422 when neither a parent nor a search is sent. Any authenticated user.',
    ],
];
