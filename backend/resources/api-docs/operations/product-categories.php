<?php

return [
    'POST /product-categories' => [
        'summary' => 'Create a product category',
        'description' => 'Creates a category (parent_id null = root). Branch-wide settings (requires_quote, management_mode single or multiple, single_quote_per_opportunity, generates_contract, multi_quote_work_order, usage_area all, requests or opportunities, multiple_open_opportunities, simplified_offer_line) are set on the root and inherited by children; a child sending a divergent value gets 422. attributes assigns catalogue attributes per context (product, quote, work_order); manager_labels maps account manager position to label. layout_source_id copies another category\'s layouts. Requires product-categories.create.',
    ],
    'POST /product-categories/bulk-move' => [
        'summary' => 'Move several product categories under a new parent',
        'description' => 'Moves every category in category_ids under parent_id (null = make them roots). All or nothing: when the destination is among the selected ones, a selected category descends from another selected one, the move would create a cycle or conflict with an inherited business function, nothing is written and 422 returns reason (self_parent, nested_selection, cycle, business_function_conflict) and the conflicts list. Inherited settings are realigned and each move is logged. Returns the number moved. Requires product-categories.update on each category.',
    ],
    'GET /product-categories/for-select' => [
        'summary' => 'List selectable product categories for a select field',
        'description' => 'Paginated option list of the selectable, active categories ordered by name; each item has id, label and meta with the effective business function, root category id and management mode. Filters: search (name), business_function_id (effective one), root_category_id (that root\'s subtree), usage_area (requests or opportunities), include_inactive. ids[] appends those categories even when outside the page. Only requires authentication.',
    ],
    'GET /product-categories/report-columns' => [
        'summary' => 'List the indicator columns available to category reports',
        'description' => 'Returns the catalogue of indicator columns of the request management report, each as key and translated label, in catalogue order. These keys are the values accepted by a category\'s report_columns. Requires product-categories.viewAny.',
    ],
    'GET /product-categories/tree' => [
        'summary' => 'Get the full product category tree',
        'description' => 'Returns the whole category tree as nested nodes (roots first, children inside children): name, parent_id, attribute and product counts, business function, the branch-wide settings, selectable/active/reportable flags and report columns. Requires product-categories.viewAny.',
    ],
    'GET /product-categories/{productCategory}' => [
        'summary' => 'Get a product category',
        'description' => 'Returns the category with its own attributes and settings plus what it inherits: inherited attributes (tagged by context), effective business function, the root each branch-wide setting comes from, inherited manager labels, effective reportable flag and report columns with their source category, and effective revenue type. Includes a permissions block. Requires product-categories.view.',
    ],
    'PUT /product-categories/{productCategory}' => [
        'summary' => 'Update a product category',
        'description' => 'Partially updates the category; only submitted fields change. A new parent_id must not be the category itself or a descendant (422); a business function cannot be set when an ancestor already defines one (422). Changing a root\'s branch-wide settings or re-parenting realigns the whole subtree; changing the revenue type queues the reclassification of the affected revenue lines. Returns the category with a permissions block. Requires product-categories.update.',
    ],
    'DELETE /product-categories/{productCategory}' => [
        'summary' => 'Delete a product category',
        'description' => 'Deletes the category and answers 204 with no content. 409, listing the usages, when it still has sub-categories, products, legacy categories pointing to it, or is used by product lines of campaigns, projects, opportunities, employment contracts or by commission configurations. Requires product-categories.delete.',
    ],
    'GET /product-categories/{productCategory}/attribute-layouts' => [
        'summary' => 'Get the form layout of a category\'s attributes',
        'description' => 'Query: context (product, quote or work_order), form_mode, exact. Without exact, returns the layout to render for form_mode (create, edit, view), falling back to the shared one. With exact=true (configurator), form_mode may also be all and the response adds the shared layout it inherits and the layout and category inherited from an ancestor. Always includes the effective attributes the layout may place. layout is sections of rows of attribute_code items, or null. Requires product-categories.view.',
    ],
    'PUT /product-categories/{productCategory}/attribute-layouts' => [
        'summary' => 'Save the form layout of a category\'s attributes',
        'description' => 'Creates or replaces the layout for context (product, quote, work_order) and form_mode (all, create, edit, view); a null layout or one without sections deletes it. layout.sections each have id, title, variant, collapsible, default_collapsed, columns (1-4), sort_order, optional visible_when, and rows of items (attribute_code, width). 422 when a code is not among the category\'s effective attributes for that context or appears twice. Returns the saved layout. Requires product-categories.update.',
    ],
    'GET /product-categories/{productCategory}/effective-attributes' => [
        'summary' => 'List the effective attributes of a category',
        'description' => 'Returns the attributes that apply to the category for the required context (product, quote or work_order): its own plus those inherited from ancestors, unless inheritance is switched off for that context. Used to build the dynamic fields of product, quote and work order forms. Requires one of product-categories.view, products.view, products.create or products.update.',
    ],
    'GET /product-categories/{productCategory}/effective-manager-labels' => [
        'summary' => 'Get the effective account manager labels of a category',
        'description' => 'Returns manager_labels: the labels of the account manager slots (position to label) that apply to the category, its own combined with those inherited from ancestors. Requires product-categories.viewAny, or one of product-categories.view, products.view, products.create, products.update.',
    ],
];
