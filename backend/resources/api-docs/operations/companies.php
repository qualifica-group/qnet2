<?php

return [
    'POST /companies' => [
        'summary' => 'Create a company',
        'description' => 'Creates a company of the group with its denomination, optional VAT number (normalised and checked for a valid format, 422 otherwise) and optional single address (line1 required when address is sent; city, province, state and country ids from the geo endpoints). Returns 201 with the company and a permissions block telling which fields and actions the caller may use. Requires companies.create.',
    ],
    'GET /companies/for-select' => [
        'summary' => 'List companies for a select',
        'description' => 'Lightweight option list of companies (id, label = denomination, subtitle = VAT number), ordered by denomination. search matches denomination or VAT number; offset and limit (max 100) paginate; ids adds those companies even outside the page or the search, to show the current value of a form, without changing the total. Response is items plus pagination with has_more. Needs only authentication.',
    ],
    'GET /companies/{company}' => [
        'summary' => 'Get a company',
        'description' => 'Returns the company with its denomination, VAT number, address (with geo names) and custom field values, plus a permissions block with the resource, field and action permissions of the caller on this record. Requires companies.view.',
    ],
    'PUT /companies/{company}' => [
        'summary' => 'Update a company',
        'description' => 'Partial update: only the fields sent are changed (denomination, vat_number, address); the address is created on first write and updated afterwards. A change to a field the caller may not edit is refused with 422. Returns the updated company with its permissions block. Requires companies.update. PATCH is accepted too.',
    ],
    'DELETE /companies/{company}' => [
        'summary' => 'Delete a company',
        'description' => 'Deletes the company together with its address; returns 204. Requires companies.delete.',
    ],
];
