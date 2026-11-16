<?php

return [
    'GET /stats/{domain}' => [
        'summary' => 'Get the statistics panel of a module',
        'description' => 'Returns the KPI widgets of a module\'s statistics panel under widgets: each is a stat (single figure), a distribution (labelled items with totals and chart type) or a trend (time series), with translated labels. domain is one of registries, referents, companies, operational-sites, company-sites, products, product-categories, projects, campaigns, leads, business-functions, users, import-runs, opportunities, tasks, quotes; any other value answers 404. Figures are cached briefly and scoped to what the actor may see. Requires the module\'s view-list permission (e.g. leads.viewAny).',
    ],
];
