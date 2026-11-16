<?php

return [
    'GET /workspaces' => [
        'summary' => 'List all workspaces',
        'description' => 'Returns every workspace (a themed app grouping a subset of the navigation menu) ordered by sort order then name, each with slug, name, description, icon, color, sort_order and navigation_keys (the menu entries it shows, in menu order). Not paginated. Super-admin only (403 otherwise).',
    ],
    'POST /workspaces' => [
        'summary' => 'Create a workspace',
        'description' => 'Creates a workspace: slug (lowercase words separated by hyphens, max 40, unique), name (max 60), optional description, icon and color (from the allowed badge icons and colors) and sort_order, and navigation_keys (at least one distinct key taken from the navigation catalogue endpoint). Returns 201 with the workspace. Super-admin only (403 otherwise).',
    ],
    'GET /workspaces/available' => [
        'summary' => 'List the workspaces I can enter',
        'description' => 'Returns workspaces, the workspaces in which the caller can see at least one menu entry, each with slug, name, description, icon, color and home_route (the route of its first visible entry), and full_view_available, whether the caller may use the full menu without a workspace (super-admins, or everyone when no workspace exists). Open to any authenticated user.',
    ],
    'GET /workspaces/navigation-catalogue' => [
        'summary' => 'List the navigation entries a workspace can include',
        'description' => 'Returns every navigation menu entry that has a route, in menu order, as key (the value to use in navigation_keys), label, icon, route and path (the labels of its parent menu groups). Super-admin only (403 otherwise).',
    ],
    'GET /workspaces/{workspace}' => [
        'summary' => 'Get a workspace',
        'description' => 'Returns the workspace with slug, name, description, icon, color, sort_order and navigation_keys in menu order. Super-admin only (403 otherwise).',
    ],
    'PUT /workspaces/{workspace}' => [
        'summary' => 'Update a workspace',
        'description' => 'Replaces the workspace with the same body as create (slug unique except for itself, navigation_keys required and replacing the whole set). The change is recorded in the activity log. Returns the workspace. Super-admin only (403 otherwise).',
    ],
    'DELETE /workspaces/{workspace}' => [
        'summary' => 'Delete a workspace',
        'description' => 'Deletes the workspace; users simply no longer see it. Returns 200 with empty data. Super-admin only (403 otherwise).',
    ],
];
