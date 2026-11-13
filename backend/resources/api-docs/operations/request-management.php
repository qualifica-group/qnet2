<?php

return [
    'POST /request-management' => [
        'summary' => 'Create a request',
        'description' => 'Creates a request: an opportunity and its offer, in one transaction. The client is either registry_id (existing registry, untouched) or client_identity with optional client_contacts and client_address (new registry; 422 when its tax code, VAT number or phone already exists). source_id and at least one product_lines row are required; reporter, commercial, supervisor, site, callback, notes, forecast, attribute values, offer lines and rewards are optional. Filling manager_slots requires request-management.assignOperator (otherwise the creator becomes the operator). Returns 201 with the work panel. Requires request-management.create.',
    ],
    'POST /request-management/assign-manager-ga1' => [
        'summary' => 'Assign the GA1 manager of many requests',
        'description' => 'Puts one user (manager_ga1_id) in the first manager slot of every request in request_ids (offer ids); manager_ga1_id is mandatory but may be null to clear the slot. Requests outside the actor\'s visibility are silently skipped. Returns assigned, the number of requests reached. Requires request-management.update and request-management.assignManagerGa1.',
    ],
    'POST /request-management/assign-operators' => [
        'summary' => 'Assign the operator of many requests',
        'description' => 'Assigns the GA2 operator of the requests in request_ids (offer ids). mode single puts operator_id on all (422 unless it is a candidate, i.e. member of the site and competent for the categories, of every reachable request); mode balanced spreads them over each request\'s candidates, optionally narrowed by operators_by_site, from the current load unless use_current_load is false. Out-of-scope requests are silently skipped; the assigned operators are notified. Returns assigned and skipped (left without a competent operator). Requires request-management.update and request-management.assignOperator.',
    ],
    'GET /request-management/category-tab-preferences' => [
        'summary' => 'Get the favourite category tabs of the current user',
        'description' => 'Returns the actor\'s favourite product categories on the requests tab strip and the "show only favourites" switch; is_default is true when nothing was saved yet. level=root reads the root-level row. Requires request-management.viewAny.',
    ],
    'PUT /request-management/category-tab-preferences' => [
        'summary' => 'Save the favourite category tabs of the current user',
        'description' => 'Replaces the actor\'s favourite product categories (favorite_category_ids, required, an empty list clears them) and the show_only_favorites switch; level=root writes the root-level row. Returns the saved preferences. Requires request-management.viewAny.',
    ],
    'POST /request-management/form-context' => [
        'summary' => 'Get the additional fields for the request creation form',
        'description' => 'Read-only despite the verb: given the product_lines picked so far (product_category_id per row, rows without a category are ignored), returns the additional attributes the create form must render and their layout. Nothing is saved. Requires request-management.create.',
    ],
    'GET /request-management/product-categories' => [
        'summary' => 'List the product category tabs of the requests',
        'description' => 'Returns the product categories having at least one request visible to the actor, each with its count of distinct requests, for the tab strip; level=root returns root categories only. Requires request-management.viewAny.',
    ],
    'POST /request-management/report' => [
        'summary' => 'Start the generation of the request statistics report',
        'description' => 'Queues the statistics report over the requests visible to the actor and answers 201 with the export run (id, status processing, file name). Inputs: optional date_from/date_to (Y-m-d), category_keys (from report/categories), row_mode (total_only, operators_only or all), optional operator_keys and site_keys (from report/operators and report/sites) and format (csv or xlsx). Poll GET /request-management/report/{exportRun} until completed, then download. Requires request-statistics.view.',
    ],
    'GET /request-management/report/categories' => [
        'summary' => 'List the categories available to the statistics',
        'description' => 'Returns the reportable category branches and their subcategories (key, label, depth, parent key) having at least one request visible to the actor, regardless of dates; their keys are the valid category_keys of the report and dashboard. Requires request-statistics.view.',
    ],
    'GET /request-management/report/category-tab-preferences' => [
        'summary' => 'Get the favourite category tabs of the statistics',
        'description' => 'Returns the actor\'s favourite categories on the statistics tab strip and the "show only favourites" switch; is_default is true when nothing was saved yet. Requires request-statistics.view.',
    ],
    'PUT /request-management/report/category-tab-preferences' => [
        'summary' => 'Save the favourite category tabs of the statistics',
        'description' => 'Replaces the actor\'s favourite statistics categories (favorite_category_ids, required, each a reportable category; an empty list clears them) and the show_only_favorites switch. Returns the saved preferences. Requires request-statistics.view.',
    ],
    'GET /request-management/report/column-statuses' => [
        'summary' => 'Get the working statuses counted by the statistics columns',
        'description' => 'Returns the status-based statistics columns (unhandled_new_contacts, current_potentials, associati, trattative_concluse, invio_presa_in_carico) with the working status ids each one counts, and every working status set: the default statuses first, then each workflow with its categories. Requires request-statistics.view.',
    ],
    'PUT /request-management/report/column-statuses' => [
        'summary' => 'Set the working statuses counted by the statistics columns',
        'description' => 'Replaces, for every status-based column, the list of working status ids it counts: columns must contain every column key and no other (an empty list switches the column off). Returns the updated configuration. Requires request-statistics.update.',
    ],
    'GET /request-management/report/dashboard' => [
        'summary' => 'Get the request statistics dashboard',
        'description' => 'Computes synchronously the statistics dashboard over the requests visible to the actor, with the same filters as the report (optional date_from/date_to, category_keys, row_mode, optional operator_keys and site_keys) passed as query parameters. Returns the applied filters, the overall summary tiles and one section per category with its tiles and charts. Requires request-statistics.view.',
    ],
    'GET /request-management/report/operators' => [
        'summary' => 'List the operators available to the statistics',
        'description' => 'Returns the GA2 operators of the requests visible to the actor (key, name, the site keys they belong to), plus an "unassigned" entry, regardless of dates and categories; their keys are the valid operator_keys of the report and dashboard. Requires request-statistics.view.',
    ],
    'GET /request-management/report/sites' => [
        'summary' => 'List the operational sites available to the statistics',
        'description' => 'Returns the operational sites of the operators of the requests visible to the actor (key and address label), regardless of dates, categories and operators; their keys are the valid site_keys of the report and dashboard. Requires request-statistics.view.',
    ],
    'GET /request-management/report/{exportRun}' => [
        'summary' => 'Get the status of a statistics report run',
        'description' => 'Returns the report run with its status (processing, completed or failed) and file name. 404 when the run belongs to another user or is not a request statistics report. Requires request-statistics.view.',
    ],
    'GET /request-management/report/{exportRun}/download' => [
        'summary' => 'Download a generated statistics report',
        'description' => 'Streams the generated CSV or XLSX file with its own content type and file name. 404 when the run is not completed, its file is missing, or it belongs to another user. Requires request-statistics.view.',
    ],
    'POST /request-management/transfer' => [
        'summary' => 'Transfer requests to another operational site',
        'description' => 'Moves the requests in request_ids (offer ids) to operational_site_id and assigns operator_id as their GA2 operator, marking them as transferred from their previous site; one activity log entry per request. Out-of-scope requests are silently skipped. The previous operator, the new operator and the supervisors are notified. Returns transferred, the number of requests moved. Requires request-management.update and request-management.transferContact.',
    ],
    'GET /request-management/{quote}' => [
        'summary' => 'Get the work panel of a request',
        'description' => 'Returns the request (an offer and its opportunity): client registry with identity, contacts and address, source, reporter, commercial, supervisor, operational site and transfer origin, operator and managers, product lines, offer lines, rewards, callback date, additional attributes with their layout, working status with the selectable statuses, general notes and forecast, plus the record and field permissions. 403 when the request is outside the actor\'s visibility. Requires request-management.view.',
    ],
    'DELETE /request-management/{quote}' => [
        'summary' => 'Delete a request',
        'description' => 'Deletes the offer behind the request and answers 204; its opportunity is kept and its name recalculated. 409 when the offer has work orders; 403 when the request is outside the actor\'s visibility. Requires request-management.delete.',
    ],
    'PUT /request-management/{quote}' => [
        'summary' => 'Update a request',
        'description' => 'Saves the submitted fields of the work panel only (sparse, PATCH accepted): client registry and its identity, contacts and address, source, reporter, commercial, supervisor, manager_slots, operational site, rewards, product_lines (replaced, never emptied), offer_lines (replaced), attribute_values, quote_workflow_status_id with the note some statuses require, callback date, general notes and forecast. New team members are notified; changes are logged on the opportunity. 422 on non-editable fields or invalid status moves; 403 out of visibility. Returns the work panel. Requires request-management.update.',
    ],
];
