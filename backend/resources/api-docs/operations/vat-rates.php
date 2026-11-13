<?php

return [
    'POST /vat-rates' => [
        'summary' => 'Create a VAT rate',
        'description' => 'Creates a VAT rate with a name and rate, the percentage (0 or more, two decimals, e.g. 22). Answers 201 with the rate and the caller\'s permissions. Requires vat-rates.create.',
    ],
    'GET /vat-rates/for-select' => [
        'summary' => 'List VAT rates for a select',
        'description' => 'Returns a page of VAT rates as options (id, label = name, meta.rate), sorted by name. Optional search filters by name; offset and limit (max 100) paginate; ids[] appends those rates even when outside the page or search, for prefilling. Response holds items and pagination. Needs only authentication.',
    ],
    'GET /vat-rates/{vatRate}' => [
        'summary' => 'Get a VAT rate',
        'description' => 'Returns the VAT rate with the caller\'s resource, field and action permissions on it. Requires vat-rates.view.',
    ],
    'PUT /vat-rates/{vatRate}' => [
        'summary' => 'Update a VAT rate',
        'description' => 'Partially updates name and rate of a VAT rate. Requires vat-rates.update.',
    ],
    'DELETE /vat-rates/{vatRate}' => [
        'summary' => 'Delete a VAT rate',
        'description' => 'Deletes a VAT rate. Answers 204 with no content; 409 when products, quote lines, invoice, purchase invoice, purchase order or purchase request lines, work order costs or agent fiscal profiles still use it, with the counts in the message. Requires vat-rates.delete.',
    ],
];
