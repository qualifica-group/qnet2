<?php

return [
    'GET /authorization/fields' => [
        'summary' => 'List the permissionable fields of every resource',
        'description' => 'Returns, under resources, every resource that supports field-level permissions with its fields (key, type, group, mandatory flag), used to build the field-permission matrix of a role. Requires roles.create or roles.update.',
    ],
    'GET /authorization/permission-catalogue' => [
        'summary' => 'Get the permission catalogue grouped by area and module',
        'description' => 'Returns, under areas, the assignable permissions and the fields (native and custom) of each module, grouped by the application menu areas; resources without a menu entry, such as notes and attachments, are listed in a trailing shared area. Used to build and display role permissions. Requires roles.viewAny, roles.create or roles.update.',
    ],
];
