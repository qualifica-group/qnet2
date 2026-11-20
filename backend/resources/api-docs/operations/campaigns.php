<?php

return [
    'POST /campaigns' => [
        'summary' => 'Create a campaign',
        'description' => 'Creates a marketing campaign; code is optional and, when omitted, generated as the next CMP-0001 style code. With project_id the campaign inherits the project\'s pipeline status, product lines and the geo levels the project fills (those fields must then be omitted); without it, country_id and at least one product_lines entry (product_category_id of a selectable category) are required. 422 when total_budget exceeds the project\'s remaining budget or the geo levels are inconsistent. Returns the campaign with a permissions block. Requires campaigns.create.',
    ],
    'GET /campaigns/for-select' => [
        'summary' => 'List campaigns for a select field',
        'description' => 'Lightweight, paginated option list (id, label = name, subtitle = code, plus operational site, source and effective product category ids) ordered by name. search matches name or code; offset/limit paginate; ids[] appends those campaigns even when outside the page (to show the current value of a form). Only requires authentication.',
    ],
    'GET /campaigns/next-code' => [
        'summary' => 'Preview the next campaign code',
        'description' => 'Returns the code the next campaign would receive (e.g. CMP-0012) as a non-binding suggestion: the final code is assigned at creation. Requires campaigns.create.',
    ],
    'GET /campaigns/{campaign}' => [
        'summary' => 'Get a campaign',
        'description' => 'Returns the campaign with its project, partner, operational site, source, pipeline status, geo scope, product lines, dates, budget and target leads, plus a permissions block for the caller. derived_from_project and geo_locked_levels tell which values come from the linked project. Requires campaigns.view.',
    ],
    'PUT /campaigns/{campaign}' => [
        'summary' => 'Update a campaign',
        'description' => 'Partially updates the campaign: only submitted fields change (code cannot be changed). Linking it to a project clears its own pipeline status and product lines, which are then read from the project, and fields the project already fills are refused. 422 when total_budget exceeds the project\'s remaining budget. Returns the updated campaign with a permissions block. Requires campaigns.update.',
    ],
    'DELETE /campaigns/{campaign}' => [
        'summary' => 'Delete a campaign',
        'description' => 'Deletes the campaign and answers 204 with no content. 409 when at least one lead references the campaign. Requires campaigns.delete.',
    ],
];
