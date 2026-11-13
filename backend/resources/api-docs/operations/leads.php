<?php

return [
    'POST /leads' => [
        'summary' => 'Create a lead',
        'description' => 'Creates a lead for a customer registry (registry_id) from a campaign (campaign_id), both required. source_id is required unless the campaign names a source, which the lead then inherits; operational_site_id, operator_id, notes, extra_fields (string map) and products_of_interest (active products whose category the campaign covers) are optional. convert_to_opportunity=true also creates the derived opportunity (and its offer) in the same transaction, and additionally requires opportunities.create. Returns 201 with the lead and the caller\'s permissions block. Requires leads.create.',
    ],
    'POST /leads/assign-operators' => [
        'summary' => 'Assign the operator of many leads',
        'description' => 'Assigns the operator of the leads in lead_ids. mode single gives every lead operator_id (required in that mode); mode balanced spreads the leads among the competent operators of the operational site of each lead\'s campaign, optionally restricted by operators_by_site and weighting current load unless use_current_load=false. operational_site_id is refused (422). Response: assigned and skipped (leads left without a competent operator, always 0 in single mode). Requires leads.update.',
    ],
    'POST /leads/convert-to-opportunities' => [
        'summary' => 'Convert many leads into opportunities',
        'description' => 'Converts every lead in lead_ids (max 200 by default) into its derived opportunity, with its offer, all or nothing. When any lead is not convertible (already has an opportunity, its campaign yields no product line, or its registry already has an open opportunity) nothing is written and the answer is 422 with errors.reason = not_convertible and the full list of blockers. Returns converted and the created opportunity_ids. Requires opportunities.create and leads.view.',
    ],
    'GET /leads/for-select' => [
        'summary' => 'List leads for a select',
        'description' => 'Lightweight option list of leads (id, label = customer registry name, subtitle = campaign code or name), ordered by registry name. search matches the registry name; registry_id narrows to one customer; offset and limit (max 100) paginate; ids adds specific leads to show a form\'s current value. Response is items plus pagination. Needs only authentication.',
    ],
    'GET /leads/{lead}' => [
        'summary' => 'Get a lead',
        'description' => 'Returns the lead with its registry (primary contacts only when the caller may see them), campaign, source, operational site, operator, derived lead_status (not_associated, associated, converted_to_opportunity), notes, extra fields, products of interest and linked opportunity, plus the caller\'s permissions block. Requires leads.view.',
    ],
    'PUT /leads/{lead}' => [
        'summary' => 'Update a lead',
        'description' => 'Partial update (PATCH accepted) of registry_id, campaign_id, operational_site_id, source_id (empty falls back to the campaign\'s source), operator_id, notes, extra_fields and products_of_interest. 422 when the new campaign does not cover the products of interest, or on a change to a field the caller may not edit. Returns the lead with its permissions block. Requires leads.update.',
    ],
    'DELETE /leads/{lead}' => [
        'summary' => 'Delete a lead',
        'description' => 'Deletes the lead; returns 204. 409 when the lead has a linked opportunity. Requires leads.delete.',
    ],
    'GET /leads/{lead}/opportunity-defaults' => [
        'summary' => 'Get the opportunity values derived from a lead',
        'description' => 'Returns what an opportunity created from this lead would inherit, to prefill its form: values and their references, the fields locked to the lead, the product lines derived from the campaign, the account manager slots (the lead\'s operator first) and existing_opportunity_id when one already exists. Requires opportunities.create and leads.view.',
    ],
];
