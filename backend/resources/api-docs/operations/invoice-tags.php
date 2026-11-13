<?php

return [
    'POST /invoice-tags' => [
        'summary' => 'Create an invoice tag',
        'description' => 'Creates a tag that can be put on invoices: name (unique, max 191), color (one of slate, gray, red, orange, amber, yellow, green, emerald, teal, blue, indigo, violet, purple, pink), optional description and is_active. sort_order cannot be sent: the tag is placed last. Returns 201 with the tag and the caller\'s permissions block. Requires invoice-tags.create.',
    ],
    'GET /invoice-tags/for-select' => [
        'summary' => 'List invoice tags for a select',
        'description' => 'Lightweight option list of invoice tags (id, label = name, meta with color and is_active) in their configured order. Only active tags unless include_inactive=true; search matches the name; offset and limit (max 100) paginate; ids adds specific tags, even inactive, so an invoice keeps showing its current one. Response is items plus pagination. Needs only authentication.',
    ],
    'POST /invoice-tags/reorder' => [
        'summary' => 'Reorder the invoice tags',
        'description' => 'Sets the display order of all invoice tags: ordered_ids must contain exactly every tag id once, in the desired order (422 if any is missing, unknown or repeated). Returns the id and new sort_order of every tag. Requires invoice-tags.update.',
    ],
    'GET /invoice-tags/{invoiceTag}' => [
        'summary' => 'Get an invoice tag',
        'description' => 'Returns the tag (name, description, color, sort order, active flag) with the caller\'s permissions block. Requires invoice-tags.view.',
    ],
    'PUT /invoice-tags/{invoiceTag}' => [
        'summary' => 'Update an invoice tag',
        'description' => 'Partial update (PATCH accepted) of name (unique), description, color and is_active; sort_order is changed only through the reorder endpoint. Returns the tag with the caller\'s permissions block. Requires invoice-tags.update.',
    ],
    'DELETE /invoice-tags/{invoiceTag}' => [
        'summary' => 'Delete an invoice tag',
        'description' => 'Deletes the tag; returns 204. 409 when an invoice still carries it, with a message saying how many. Requires invoice-tags.delete.',
    ],
];
