<?php

return [
    'POST /agent-sales-report/exports/details' => [
        'summary' => 'Download the agent sales report details as Excel',
        'description' => 'Synchronously generates an xlsx file over the offers selected by the agent sales report grid state (sortModel, filterModel, advancedFilters, customFilterRules, search; format is always xlsx and columns is ignored). For each offer it writes the offer row, its revenue lines with supervisor, commercial and reporter commissions and net amount, and a commission totals row. Only offers visible to the caller are included. 422 when the selection exceeds the export row cap. Requires agent-sales-report.view.',
    ],
    'POST /agent-sales-report/exports/lines' => [
        'summary' => 'Download the agent sales report lines as Excel',
        'description' => 'Synchronously generates an xlsx file with one flat row per revenue line of the offers selected by the agent sales report grid state (same body as the details export): offer code, date, customer, product category, product, quantity, prices, commissions per role and net amount. Offers without revenue lines produce no rows; only offers visible to the caller are included. 422 when the selection exceeds the export row cap. Requires agent-sales-report.view.',
    ],
];
