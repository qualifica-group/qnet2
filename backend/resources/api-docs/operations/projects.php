<?php

return [
    'GET /projects' => [
        'summary' => 'List projects as cards',
        'description' => 'Returns projects newest first, paginated with offset/limit, each with code, name, description, pipeline status, geographic scope, campaigns and leads counts, total, allocated and remaining budget, dates and the actor\'s can.update/can.delete flags. Filters: search (code or name), pipeline_status_id, and advancedFilters using the same keys as the projects table. The response holds items and pagination. Requires projects.viewAny.',
    ],
    'POST /projects' => [
        'summary' => 'Create a project',
        'description' => 'Creates a project. name, country_id, start_date and product_lines (at least one row, each with a selectable product_category_id; the business function is derived from the category) are required. code is generated as PRJ-0001... when omitted; a missing pipeline_status_id defaults to the system "new" status. state, province and city must belong to the chosen country hierarchy; partner_id references a referent, operational_site_id an operational site; end_date cannot precede start_date. Returns 201 with the project and the permissions block. Requires projects.create.',
    ],
    'GET /projects/for-select' => [
        'summary' => 'List projects for a select',
        'description' => 'Returns projects as options labelled "code - name", ordered by name, searchable by code or name and paginated with offset/limit (max 100; include_total=false reports has_more instead of the total). Each option\'s meta carries the defaults a new campaign inherits: partner, pipeline status, geography, product lines, operational site, start date and total/allocated/remaining budget. ids[] appends already-saved projects. Requires authentication only.',
    ],
    'GET /projects/next-code' => [
        'summary' => 'Preview the next project code',
        'description' => 'Returns, as code, the next sequential project code (PRJ-0001...) to prefill the create form. It is only a suggestion: the code is assigned for real when the project is created without one. Requires projects.create.',
    ],
    'GET /projects/summary' => [
        'summary' => 'Get the project KPI counters',
        'description' => 'Returns three global counters: projects_count, campaigns_count (campaigns linked to a project) and leads_count (leads of those campaigns). Requires projects.viewAny.',
    ],
    'GET /projects/{project}' => [
        'summary' => 'Get a project',
        'description' => 'Returns the project with pipeline status, geography (country, state, province, city and resulting scope), product lines, partner, operational site, dates, total budget, lead target, budget allocated to campaigns and remaining, campaigns count, custom fields and the permissions block. Requires projects.view.',
    ],
    'PUT /projects/{project}' => [
        'summary' => 'Update a project',
        'description' => 'Partially updates the project with the create fields; code is immutable. product_lines, when sent, replaces the rows and cannot be emptied. Changing the geography realigns the geography of the campaigns linked to the project in the same transaction. Returns the updated project and the permissions block. Requires projects.update.',
    ],
    'DELETE /projects/{project}' => [
        'summary' => 'Delete a project',
        'description' => 'Deletes the project. Refused with 409 when it has campaigns. Returns 204 no content. Requires projects.delete.',
    ],
];
