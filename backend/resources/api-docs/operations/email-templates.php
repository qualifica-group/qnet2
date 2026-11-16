<?php

return [
    'POST /email-templates' => [
        'summary' => 'Create an email template',
        'description' => 'Creates a reusable email template for one module (module: work_orders, invoices or purchase_orders) with name, subject and HTML body; subject and body may contain {category.key} variables listed by the variables endpoint. The body is sanitized before saving. is_active defaults to active. Answers 201 with the template and the permissions block. 422 when the name already exists in that module. Requires email-templates.create.',
    ],
    'GET /email-templates/for-select' => [
        'summary' => 'List email templates for a select',
        'description' => 'Returns the active email templates of one module (module is required: work_orders, invoices or purchase_orders) as id and label, ordered by name, searchable by name and paginated (items + pagination). ids[] adds those templates even outside the page. Requires authentication only.',
    ],
    'GET /email-templates/variables' => [
        'summary' => 'List the variables usable in email templates',
        'description' => 'Returns the variable catalogue of one module (module is required: work_orders, invoices or purchase_orders): a list of categories, each with key, label and its variables (variable token such as {category.key}, label, type and an example value). Requires email-templates.view.',
    ],
    'GET /email-templates/{emailTemplate}' => [
        'summary' => 'Get an email template',
        'description' => 'Returns the template with its module, name, subject, HTML body, description and active flag, plus the permissions block of the caller on it. Requires email-templates.view.',
    ],
    'PUT /email-templates/{emailTemplate}' => [
        'summary' => 'Update an email template',
        'description' => 'Partially updates name, subject, body, description and is_active; only submitted fields change and the body is sanitized. The module cannot be changed (422 when sent). 422 when the name already exists in the same module. Requires email-templates.update.',
    ],
    'DELETE /email-templates/{emailTemplate}' => [
        'summary' => 'Delete an email template',
        'description' => 'Deletes the template and answers 204 with no content. 409 when emails have already been composed from it: the message states how many. Requires email-templates.delete.',
    ],
];
