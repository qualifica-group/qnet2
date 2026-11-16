<?php

return [
    'GET /management-control/account-flows' => [
        'summary' => 'Get collections and payments by financial account',
        'description' => 'Read-only report of the collections and payments of the period (date_from, date_to, inclusive, at most 24 months; a missing date leaves that side open), per financial account and per month, on the document total. Optional company_id[], company_site_id[] and operational_site_id[] filters. Returns applied (the normalized filters), collections and payments (total, installments, by_account) and by_month. 422 when the interval exceeds 24 months. Requires management-control.view.',
    ],
    'GET /management-control/agents' => [
        'summary' => 'Get the agents commissions report',
        'description' => 'Read-only report per agent of the commissions accrued in the period, still to settle, in a statement, approved and unpaid, and paid in the period. The top agents by amount to settle (top, default 20, max 50) are listed one by one and the rest summed in others. Filters: date_from, date_to (inclusive, at most 24 months), company_id[]. Returns applied, today, totals, agents, others and by_month. Requires management-control.view.',
    ],
    'GET /management-control/billing' => [
        'summary' => 'Get the billing report',
        'description' => 'Read-only report with the backlog (contract revenue still to invoice on open work orders, as of today, possibly negative when over-invoiced, grouped by group_by: business_function (default), company or commercial, and by age) and the proforma cycle of the period (issued, converted to invoice with average days, still open by age, average days from invoice to collection). date_from and date_to bound only the proforma cycle; company_id[], company_site_id[] and operational_site_id[] filter both. Returns applied, today, backlog and proforma. Requires management-control.view.',
    ],
    'GET /management-control/cash-flow' => [
        'summary' => 'Get the cash flow forecast',
        'description' => 'Read-only forecast, as of today, of the open receivable and payable installments and the open purchase commitments, projected by expected month over horizon_months (default 6, max 24) from the current month: the overdue backlog, one row per month with the running balance, and what falls beyond the horizon, in total and per company. document_type is all (default), proforma or invoice. Optional company_id[], company_site_id[] and operational_site_id[] filters. Returns applied, today, backlog, months, beyond and by_company. Requires management-control.view.',
    ],
    'GET /management-control/exposure' => [
        'summary' => 'Get the receivables and payables exposure report',
        'description' => 'Read-only report with the receivable and payable schedules (open, overdue, aging, by status and top counterparties), the open purchase commitments, the net exposure in total and by group_by (company default, company_site or operational_site) and the customer concentration (top customers, default 10, max 25). Open amounts and aging are a snapshot at today; date_from and date_to bound only the flows of the period (invoiced, settled, average days, concentration). document_type is invoice (default), proforma or all. Requires management-control.view.',
    ],
    'GET /management-control/income-statement' => [
        'summary' => 'Get the income statement by business function',
        'description' => 'Read-only income statement of the work orders selected by date_from/date_to (on the work order start date), company_id[], company_site_id[], operational_site_id[], business_function_id[], work_order_type[] (processing, project) and commercial_id[] (referents). revenue_basis picks the actual revenue: contract (default), invoiced or collected. Returns applied, totals (budget and actual revenue, costs, commissions, margins and deltas), by_business_function, and segments split by segment_by: none (default), company, company_site, operational_site, work_order_type or commercial. Requires management-control.view.',
    ],
    'GET /management-control/monthly-trend' => [
        'summary' => 'Get the monthly trend by business function',
        'description' => 'Read-only report of revenue, costs (internal labor cost included) and margin for every month between the required date_from and date_to (at most 24 months), per business function, with totals compared to the same months one year earlier and costs by nature. Optional company_id[], company_site_id[] and operational_site_id[] filters. Returns applied, months, functions, totals and by_nature. 422 when the interval exceeds 24 months. Requires management-control.view.',
    ],
    'GET /management-control/structure-costs' => [
        'summary' => 'Get the structure costs report',
        'description' => 'Read-only report of the passive costs not tied to any work order: committed (purchase order lines not yet invoiced) and invoiced (purchase invoice lines, excluding commission statement invoices), by cost type and by group_by: company (default), company_site or operational_site. date_from and date_to apply to the order date and the invoice document date; company_id[], company_site_id[] and operational_site_id[] filter. Returns applied, totals, by_cost_type and by_dimension. Requires management-control.view.',
    ],
];
