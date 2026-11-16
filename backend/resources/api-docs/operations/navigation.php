<?php

return [
    'GET /navigation' => [
        'summary' => 'Get the navigation menu of the current user',
        'description' => 'Returns the menu tree the caller is allowed to see, already filtered by their permissions: each item has key, label, icon, route, type and children. With workspace=<slug> it returns only that workspace\'s menu (404 when the slug is unknown or the caller sees nothing in it; the slugs come from GET /workspaces/available). Without it, the full menu is returned only to super-admins or when no workspace is configured, otherwise an empty list. Needs only authentication.',
    ],
];
