<?php

return [
    'GET /proforma-requests/{proformaRequest}' => [
        'summary' => 'Get a proforma request',
        'description' => 'Returns the request raised from a work order for Accounting: kind (consultancy = the consultancy lines of the work order, institution = the lines of one supplier), status (pending or issued), note, work order, offer, company, customer, supplier, payment method, the issued invoice when present and who assigned it to whom, plus the record and field permissions. Requires proforma-requests.view.',
    ],
    'PATCH /proforma-requests/{proformaRequest}' => [
        'summary' => 'Update the note of a proforma request',
        'description' => 'Replaces the note of the request (required, max 5000 characters); no other field is editable. Returns the request. Requires proforma-requests.update.',
    ],
    'DELETE /proforma-requests/{proformaRequest}' => [
        'summary' => 'Delete a proforma request',
        'description' => 'Deletes a pending request and answers 204. 409 when the request is already issued or linked to an invoice. Requires proforma-requests.delete.',
    ],
    'POST /proforma-requests/{proformaRequest}/invoice' => [
        'summary' => 'Issue the proforma invoice of a request',
        'description' => 'Creates the proforma invoice of the request from the header (document_date, company_id, customer_registry_id, active payment_method_id, optional financial_account_id bank of that company, notes, layout) and lines (offer lines the request bills, each in one document only, or free lines). It is numbered per company and year, gets a payment schedule from the payment method, and the request becomes issued. 409 when already invoiced; 422 on a non-positive total, a line not billable by this request or a bank of another company. Returns 201 with the invoice. Requires invoices.create and proforma-requests.view.',
    ],
    'GET /proforma-requests/{proformaRequest}/invoice-draft' => [
        'summary' => 'Get the draft of the invoice to issue for a request',
        'description' => 'Returns what the issue form needs, nothing is saved: the request, work order and offer, the defaults (today\'s date, company, customer, payment method, the company bank when it has only one), the offer lines available for this request with quantities, prices, VAT, proposed revenue type and commission, and the selectable bank accounts. 409 when the request is already invoiced. Requires invoices.create and proforma-requests.view.',
    ],
];
