<?php

return [
    'POST /roles' => [
        'summary' => 'Create a role',
        'description' => 'Creates a role with a unique name. permissions is a list of existing permission names (e.g. leads.view); users the ids of the members; field_permissions entries (resource, field, visible, editable, required) restrict single fields of a module, and must name a registered resource and one of its fields (422 otherwise). Super-admin membership can only be granted by a super-admin. Returns the role with a permissions block. Requires roles.create.',
    ],
    'GET /roles/for-select' => [
        'summary' => 'List assignable roles for a select field',
        'description' => 'Lightweight, paginated option list (id, label = name) of the roles the caller is allowed to assign, ordered by name: the super-admin role is listed only for a super-admin. search matches the name; offset/limit paginate. Only requires authentication.',
    ],
    'GET /roles/{role}' => [
        'summary' => 'Get a role',
        'description' => 'Returns the role with its permission names, member user ids and field-level permissions, plus a permissions block for the caller. Requires roles.view.',
    ],
    'PUT /roles/{role}' => [
        'summary' => 'Update a role',
        'description' => 'Partially updates the role: a submitted name, permissions, users or field_permissions replaces the current value; omitted keys are untouched. The super-admin role cannot be renamed or have its permissions changed (422); its members can be changed only by a super-admin and the last super-admin cannot be removed. Returns the role with a permissions block. Requires roles.update.',
    ],
    'DELETE /roles/{role}' => [
        'summary' => 'Delete a role',
        'description' => 'Deletes the role and answers 204 with no content. 422 for the super-admin system role; 409 when the role is still assigned to users. Requires roles.delete.',
    ],
];
