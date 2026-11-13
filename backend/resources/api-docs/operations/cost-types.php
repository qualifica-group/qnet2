<?php

return [
    'POST /cost-types' => [
        'summary' => 'Create a cost type',
        'description' => 'Creates a cost type, the lookup that classifies purchase costs. name is unique; nature is direct (chargeable to a work order), indirect (production cost to apportion) or general (overhead); color is a badge colour token (slate, gray, red, orange, amber, yellow, green, emerald, teal, blue, indigo, violet, purple, pink). chart_account_id places it under a cost account of the chart of accounts and then requires code, unique across chart accounts, cost types and revenue types. sort_order is server-managed (appended last). Returns 201 with the cost type. Requires cost-types.create.',
    ],
    'GET /cost-types/for-select' => [
        'summary' => 'List cost types for a select',
        'description' => 'Returns active cost types as select options (id, label, colour, nature, code and chart-of-accounts path), the ones placed in the chart of accounts first by code, then the others by name. search matches code and name; offset and limit page the list; ids[] adds the given ids, even inactive, to hydrate an existing value. Responds with items and pagination. Needs only authentication.',
    ],
    'POST /cost-types/reorder' => [
        'summary' => 'Reorder the cost types',
        'description' => 'Resequences the cost types to the order of ordered_ids, which must contain every cost type id exactly once (422 for duplicates, unknown or missing ids). Returns each id with its new sort_order. Requires cost-types.update.',
    ],
    'GET /cost-types/{costType}' => [
        'summary' => 'Get a cost type',
        'description' => 'Returns the cost type with its nature, colour, chart-of-accounts placement and code, sort order, active flag and whether the actor can update or delete it. Requires cost-types.view.',
    ],
    'PUT /cost-types/{costType}' => [
        'summary' => 'Update a cost type',
        'description' => 'Updates the submitted fields of the cost type (partial update; same rules as creation, sort_order cannot be set here: use the reorder endpoint). Deactivate it with is_active false to stop offering it in selects. Returns the cost type. Requires cost-types.update.',
    ],
    'DELETE /cost-types/{costType}' => [
        'summary' => 'Delete a cost type',
        'description' => 'Deletes the cost type and answers 204. 409, listing the uses, when it is still referenced by agent fiscal profiles, products, purchase invoices or their lines, purchase order lines, treasury movements or work order costs (deactivate it instead). Requires cost-types.delete.',
    ],
];
