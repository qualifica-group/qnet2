<?php

return [
    'POST /project-work-orders' => [
        'summary' => 'Create a project work order without an offer',
        'description' => 'Creates a project work order (commessa di progetto) that comes from no offer (spec 0431): type is always project, no quote_id nor quote_line_ids. Required: title, start_date, registry_id (client), company_id and product_lines (at least one {product_category_id}, the business function is derived from the category). Optional: company_site_id (a site of that company), operational_site_id, product_ids (active products in place of offer lines: their categories bring the additional information attribute_values), supervisor_ids, participant_slots, an active task_template_id whose tasks are generated, attribute_values. Code is generated (COM-0001...) when omitted. The client, company, sites, product_lines and product_ids are then edited with PATCH /work-orders/{workOrder}. Answers 201 with the work order (is_standalone true). Requires work-orders.create.',
    ],
];
