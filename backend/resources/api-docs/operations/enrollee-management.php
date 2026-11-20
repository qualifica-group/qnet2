<?php

return [
    'POST /enrollee-management/assign-manager-ga1' => [
        'summary' => 'Assign the first account manager of many enrollees',
        'description' => 'Sets the user manager_ga1_id (required key; null clears it) as first account manager (GA1 slot) of every offer in request_ids. Ids outside the caller\'s visibility scope are silently skipped; assigned reports how many offers were reached. No notification is sent. Requires enrollee-management.update and enrollee-management.assignManagerGa1.',
    ],
    'POST /enrollee-management/assign-operators' => [
        'summary' => 'Assign the operator of many enrollees',
        'description' => 'Assigns the operator of the offers in request_ids. mode single gives every offer operator_id (422 when that user is not enabled for the operational site or product categories of some offer); mode balanced spreads the offers among the competent operators of each offer\'s own site, optionally restricted by operators_by_site and weighting current load unless use_current_load=false. Out-of-scope ids are skipped; response: assigned and skipped (offers left without a competent operator). Assignees are notified. Requires enrollee-management.update and enrollee-management.assignOperator.',
    ],
    'GET /enrollee-management/category-tab-preferences' => [
        'summary' => 'Get the caller\'s favourite enrollee categories',
        'description' => 'Returns the caller\'s favourite product categories on the enrollee category tab strip (favorite_category_ids, show_only_favorites) and is_default=true when nothing was saved yet and the list is the default derived from the caller\'s competence. level=root reads the preference of the root-categories strip. Requires enrollee-management.viewAny.',
    ],
    'PUT /enrollee-management/category-tab-preferences' => [
        'summary' => 'Save the caller\'s favourite enrollee categories',
        'description' => 'Stores the caller\'s favourite product categories for the enrollee tab strip: favorite_category_ids (array, may be empty) and show_only_favorites (boolean), both required; level=root saves the preference of the root-categories strip. Returns the saved preference. Requires enrollee-management.viewAny.',
    ],
    'GET /enrollee-management/product-categories' => [
        'summary' => 'List the product categories with enrollees',
        'description' => 'Feeds the enrollee category tabs: only the product categories having at least one enrollee (offer in a validated or closed-won status) within the caller\'s visibility scope, each with its distinct requests_count. level=root returns root categories only. Requires enrollee-management.viewAny.',
    ],
    'POST /enrollee-management/transfer' => [
        'summary' => 'Transfer enrollees to another operational site',
        'description' => 'Moves every offer in request_ids to operational_site_id and assigns operator_id as its operator, both required. Each offer is flagged as transferred with its origin site, an activity log entry is written and the operators losing and gaining it are notified. Out-of-scope ids are silently skipped; transferred reports how many offers were moved. Requires enrollee-management.update and enrollee-management.transferContact.',
    ],
    'GET /enrollee-management/{quote}' => [
        'summary' => 'Get the work panel of an enrollee',
        'description' => 'Returns the enrollee work panel for the offer {quote}: customer identity, contacts and address, referent, opportunity data and notes, attribution (source, reporter, commercial, supervisor, operational site, transfer origin), operator and account managers, product and offer lines, rewards, additional information fields, workflow status with the statuses it may move to, next callback and forecast, plus the caller\'s permissions block. 403 when the offer is not an enrollee (status not validated or closed-won) or outside the caller\'s visibility scope. Requires enrollee-management.view.',
    ],
    'DELETE /enrollee-management/{quote}' => [
        'summary' => 'Delete an enrollee',
        'description' => 'Deletes the offer behind the enrollee row (its lines go with it; the opportunity is kept and its name recalculated); returns 204. 409 when the offer already has work orders; 403 when out of scope. Requires enrollee-management.delete.',
    ],
    'PUT /enrollee-management/{quote}' => [
        'summary' => 'Update the work panel of an enrollee',
        'description' => 'Sparse update (PATCH accepted): only the keys sent change, written on the offer or its opportunity. Covers customer, client identity/contacts/address, attribution, manager_slots, rewards, product_lines, offer_lines (full replacement), attribute_values, quote_workflow_status_id with note when the destination requires it, next_callback_at, general_notes and forecast. A status change can create or suspend the contract. A change to a non-editable field is a 422; 403 when out of scope. Requires enrollee-management.update. Returns the work panel.',
    ],
];
