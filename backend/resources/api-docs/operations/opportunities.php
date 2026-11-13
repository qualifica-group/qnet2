<?php

return [
    'POST /opportunities' => [
        'summary' => 'Create an opportunity',
        'description' => 'Creates a sales opportunity. product_lines (at least one product_category_id usable for opportunities) is required; registry_id is required unless lead_id is given, in which case it comes from the lead and a linked quote is also created. name is optional (automatic when empty); the status is computed from the quotes. Optional: commercial_id, reporter_id, referent_id (referents), supervisor_id and manager_slots (users), rewards, products_of_interest. 422 when the registry already has an open opportunity, unless its categories allow several. Newly assigned people are notified. Returns 201. Requires opportunities.create.',
    ],
    'GET /opportunities/for-select' => [
        'summary' => 'List opportunities for a dropdown',
        'description' => 'Returns the opportunities visible to the caller as id, label (name) and meta (commercial, reporter, supervisor, operational site, lead source, managers), ordered by name. Filters: search (name contains) and registry_id; ids[] also returns those opportunities for display of a saved value. Paginated with offset and limit; include_total=false skips the count and reports has_more instead. Response is a list (items + pagination). Requires authentication only.',
    ],
    'GET /opportunities/{opportunity}' => [
        'summary' => 'Get an opportunity',
        'description' => 'Returns the opportunity with its registry, referent, commercial, reporter, supervisor, operational site, product lines, products of interest, rewards, managers, originating lead and computed status, the count of its quotes visible to the caller, plus the caller\'s resource, field and action permissions on it. Requires opportunities.view and the opportunity within the caller\'s visibility (view all, supervisor, manager or competence), otherwise 403.',
    ],
    'PUT /opportunities/{opportunity}' => [
        'summary' => 'Update an opportunity',
        'description' => 'Partially updates the opportunity; product_lines, products_of_interest, manager_slots and rewards replace the whole collection when sent. lead_id and the status cannot be sent; on an opportunity born from a lead, registry_id may only be resent unchanged. A new reporter retargets the existing rewards. 422 when products of interest fall outside the product line categories. Newly assigned supervisor and managers are notified. Returns the opportunity with permissions. Requires opportunities.update and the opportunity within the caller\'s visibility.',
    ],
    'DELETE /opportunities/{opportunity}' => [
        'summary' => 'Delete an opportunity',
        'description' => 'Deletes the opportunity; the originating lead is left untouched. 409 when the opportunity has quotes. Returns 204 no content. Requires opportunities.delete on the record.',
    ],
];
