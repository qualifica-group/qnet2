<?php

return [
    'POST /tables/{domain}/bulk-delete' => [
        'summary' => 'Delete several rows of a table',
        'description' => 'Deletes the rows whose ids are listed (ids, at least one) in the {domain} table (for example leads, quotes, tasks, users), one by one: a row that fails never undoes the others. Each row goes through the same per-row delete permission and domain guards as the single delete. Returns deleted (count) and failed, a list of {id, reason} with reason not_found, forbidden or guarded. 404 for an unknown domain. Requires the list permission (viewAny) of the domain.',
    ],
    'GET /tables/{domain}/columns' => [
        'summary' => 'Get the table configuration of a domain',
        'description' => 'Returns the table schema of {domain} (for example leads, quotes, work-orders, tasks, users) for the caller: columns (id, type, filter type, sortable, editable, options) merged with the caller saved layout, column filters, row actions, default sort and pagination, searchable columns, the advanced filter catalogue and the caller saved filters. Use it to learn the column ids accepted by the rows, values and preferences endpoints. Optional product_category_id, opportunity_id, quote_id, registry_id narrow the domains that support them. 404 for an unknown domain. Requires the list permission (viewAny) of the domain.',
    ],
    'GET /tables/{domain}/filter-views' => [
        'summary' => 'List the saved filter views of a table',
        'description' => 'Returns the filter views of {domain} visible to the caller: their own views plus the views other users shared, each with name, column filters, advanced filters, custom rules, visibility (private or shared), whether the caller owns it, the owner name of a shared view and the caller favorite flag. Requires the list permission (viewAny) of the domain.',
    ],
    'POST /tables/{domain}/filter-views' => [
        'summary' => 'Save a filter view of a table',
        'description' => 'Saves a named set of filters for {domain}, owned by the caller: filters (the column filter model, may be empty), optional advancedFilters, and optional rules ({and: [], or: []}) which, when present, make it a custom rule view saved without the other filters. visibility private or shared; shared requires table-filter-views.publish. 422 when the caller already has a view with that name in the domain. Answers 201. Requires the list permission (viewAny) of the domain.',
    ],
    'PUT /tables/{domain}/filter-views/{filterView}' => [
        'summary' => 'Update a saved filter view',
        'description' => 'Replaces name, visibility, filters, advancedFilters and rules of a filter view of {domain}, with the same rules as saving one. Only the owner may update it (403 otherwise); shared visibility requires table-filter-views.publish. 404 when the view does not belong to the domain.',
    ],
    'DELETE /tables/{domain}/filter-views/{filterView}' => [
        'summary' => 'Delete a saved filter view',
        'description' => 'Deletes a filter view of {domain} and answers 204 with no content. Only the owner may delete it (403 otherwise). 404 when the view does not belong to the domain.',
    ],
    'POST /tables/{domain}/filter-views/{filterView}/favorite' => [
        'summary' => 'Mark a filter view as favorite',
        'description' => 'Marks a filter view (own or shared) as favorite for the caller; repeating the call changes nothing. Returns the view. 404 when the view is not in {domain} or is another user private view. Requires the list permission (viewAny) of the domain.',
    ],
    'DELETE /tables/{domain}/filter-views/{filterView}/favorite' => [
        'summary' => 'Remove a filter view from favorites',
        'description' => 'Removes the favorite mark of the caller from a filter view; repeating the call changes nothing. Returns the view. 404 when the view is not in {domain} or is another user private view. Requires the list permission (viewAny) of the domain.',
    ],
    'POST /tables/{domain}/filters' => [
        'summary' => 'Save the applied filters of a table',
        'description' => 'Stores the column filter model (filterModel) and/or the advanced filters (advancedFilters) the caller has applied on {domain}, so they are restored on the next visit; a key left out keeps its saved value, an empty object clears it. Returns the merged table configuration, as the columns endpoint. Requires the list permission (viewAny) of the domain.',
    ],
    'DELETE /tables/{domain}/filters' => [
        'summary' => 'Clear the saved filters of a table',
        'description' => 'Deletes the filters the caller saved on {domain} and answers 204 with no content. Requires the list permission (viewAny) of the domain.',
    ],
    'POST /tables/{domain}/preferences' => [
        'summary' => 'Save the column layout of a table',
        'description' => 'Stores the caller column layout for {domain}: columns lists column ids (from the columns endpoint) with visible, width (50 to 1000) and order. Applies to the caller only. Returns the merged table configuration, as the columns endpoint. 422 for an unknown column id. Requires the list permission (viewAny) of the domain.',
    ],
    'DELETE /tables/{domain}/preferences' => [
        'summary' => 'Reset the column layout of a table',
        'description' => 'Deletes the caller saved column layout for {domain}, restoring the default one, and answers 204 with no content. Requires the list permission (viewAny) of the domain.',
    ],
    'POST /tables/{domain}/rows' => [
        'summary' => 'List the rows of a table',
        'description' => 'The list endpoint of every module: a page of {domain} rows visible to the caller (items keyed by column id + pagination with total; meta.aggregates when the domain has totals). startRow and endRow (exclusive) select the page, capped per domain. Optional: sortModel [{colId, sort}], filterModel keyed by column id in AG Grid form ({filterType, type, filter} or {filterType: set, values}), search, advancedFilters, customFilterRules ({and, or}, replaces the other filters) and scoping keys (registryId, opportunityId, quoteId...) accepted by some domains only. Requires the list permission (viewAny) of the domain.',
    ],
    'PATCH /tables/{domain}/rows/{row}' => [
        'summary' => 'Edit one cell of a table row',
        'description' => 'Sets the value of one editable column (column, a column id marked editable by the columns endpoint) on the row {row} of {domain}; note is accepted only by the columns that support it. Returns the updated row as the rows endpoint maps it. 404 when the row is outside the caller visible rows; 403 without update rights on the row or on that field; 422 when the column is not editable, a required field is left blank or the value is invalid.',
    ],
    'POST /tables/{domain}/values' => [
        'summary' => 'List the distinct values of a table column',
        'description' => 'Returns values, the distinct values of columnId (a filterable column of {domain}) for an Excel-like set filter, and hasMore. The list honours the filters active on the other columns (filterModel) but never the column own filter; search narrows it and limit caps it (1 to 200). The same scoping keys as the rows endpoint are accepted. Requires the list permission (viewAny) of the domain.',
    ],
];
