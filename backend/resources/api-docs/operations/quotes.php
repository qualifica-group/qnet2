<?php

return [
    'POST /quotes' => [
        'summary' => 'Create a quote',
        'description' => 'Creates an offer on an opportunity (opportunity_id) with at least one offer_lines row (product, supplier, quantity, unit_price, VAT, commissions) and optional cost_lines, roles, account managers (manager_slots, inherited from the opportunity when omitted), rewards and attribute_values. code defaults to QUO-0001...; totals, title and status are computed; an explicit quote_workflow_status_id must fit the resolved workflow, with note when required. Moving to a won status creates the contract; managers are notified. 422 when the opportunity allows a single offer already present. Requires quotes.create.',
    ],
    'POST /quotes/commission-defaults' => [
        'summary' => 'Preview the default commissions of a quote line',
        'description' => 'Read-only: for a product_id and the line net amount (line_net_amount), returns the commissions the current rules would apply to the line for each role (commercial, reporter, supervisor, supplier), with recipient, type (FIXED_AMOUNT or PERCENTAGE), value, calculated amount and origin, plus the supplier commission direction (PAID or RECEIVED, null when the product typology has no supplier commission). Role ids sent override those of quote_id. Requires quotes.create, or quotes.update on quote_id, and edit rights on commissions (403 otherwise).',
    ],
    'POST /quotes/commission-recipients' => [
        'summary' => 'Get the allowed commission recipients of a quote line',
        'description' => 'Read-only: returns, for each commission role (COMMERCIAL, REPORTER, SUPERVISOR, SUPPLIER), the only recipient a line may award it to (type, id, name), or null when no one is selected for that role. Recipients come from the submitted commercial_id, reporter_id, supervisor_id and the line supplier_id, falling back to those of quote_id. Requires quotes.create, or quotes.update on quote_id, and visibility of commissions and their recipients (403 otherwise).',
    ],
    'GET /quotes/for-select' => [
        'summary' => 'List quotes for a select',
        'description' => 'Returns quotes as options labelled "code - title", ordered by code, searchable by code or title and filterable by registry_id (the client of the quote\'s opportunity). Paginated with offset/limit (max 100; include_total=false reports has_more instead of the total); ids[] appends already-saved quotes outside the filters. Requires authentication only.',
    ],
    'POST /quotes/form-context' => [
        'summary' => 'Get the additional fields for a quote being composed',
        'description' => 'Read-only: given an optional opportunity_id, optional product_category_ids (the categories of an opportunity not saved yet) and the product_id of the offer lines composed so far, returns the dynamic attributes that apply to the quote (applicable_attributes) and their form layout (attribute_layout), so the create form can show and validate the right additional fields. Nothing is saved. Requires quotes.create.',
    ],
    'POST /quotes/with-opportunity' => [
        'summary' => 'Create an opportunity and its quote together',
        'description' => 'Creates a new opportunity and its first quote in one transaction. Body: opportunity (same fields as POST /opportunities, without lead_id) and quote (same fields as POST /quotes, without opportunity_id; the quote account managers are added to the new opportunity). Validation errors are keyed opportunity.<field> or quote.<field>; any error creates nothing. 422 on opportunity.registry_id with opportunity.existing_opportunity_id when the registry already has an open opportunity, unless its categories allow several. Returns 201 with the created quote, as POST /quotes. Requires opportunities.create and quotes.create.',
    ],
    'GET /quotes/next-code' => [
        'summary' => 'Preview the next quote code',
        'description' => 'Returns, as code, the next sequential quote code (QUO-0001...) to prefill the create form. It is only a suggestion: the code is assigned for real when the quote is created without one. Requires quotes.create.',
    ],
    'GET /quotes/{quote}' => [
        'summary' => 'Get a quote',
        'description' => 'Returns the quote with opportunity, client registry and referent, workflow status and the statuses it can move to, commercial roles, account managers, company and sites, layout, payment method, rewards, linked contract, offer and cost lines with their commissions, dynamic attributes with layout, totals summary, custom fields and the permissions block. Requires quotes.view.',
    ],
    'PUT /quotes/{quote}' => [
        'summary' => 'Update a quote',
        'description' => 'Partially updates the quote; opportunity_id cannot change. offer_lines and cost_lines, when sent, replace the lines (offer lines cannot be emptied); manager_slots replaces the account managers; attribute_values are merged. Totals, commission amounts, title and opportunity name are recomputed. A status change follows the workflow rules (note when required), is logged, creates the contract when it becomes won or suspends it when it leaves won; managers are notified. Requires quotes.update and edit rights on the submitted fields.',
    ],
    'DELETE /quotes/{quote}' => [
        'summary' => 'Delete a quote',
        'description' => 'Deletes the quote with its lines and recomputes the name of its opportunity. Refused with 409 when any of its lines has work orders. Returns 204 no content. Requires quotes.delete.',
    ],
    'GET /quotes/{quote}/commission-recalculation' => [
        'summary' => 'Preview a commission recalculation',
        'description' => 'Returns what recalculating the quote\'s commissions would touch: lines_count (offer lines with a product), commissions_count, manual_commissions_count (manually overridden) and closed (true when the quote is won or lost and cannot be recalculated). Requires quotes.view.',
    ],
    'POST /quotes/{quote}/document' => [
        'summary' => 'Download the quote PDF',
        'description' => 'Generates the quote document as a PDF named <code>.pdf from the quote\'s layout, or the default active quote layout when none is set, and streams it as a download; nothing is stored. 422 when no layout is available or the layout configuration is invalid. Requires quotes.view.',
    ],
    'POST /quotes/{quote}/recalculate-commissions' => [
        'summary' => 'Recalculate the commissions of a quote',
        'description' => 'Realigns the commissions of an open quote to the current commission rules. mode is KEEP_MANUAL (refresh only rule-driven commissions, keep manual overrides) or REGENERATE_ALL (rebuild every commission from the rules). 422 when the quote is won or lost. Returns the updated quote and the permissions block. Requires quotes.update and edit rights on commissions.',
    ],
];
