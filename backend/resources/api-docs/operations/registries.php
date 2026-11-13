<?php

return [
    'POST /registries' => [
        'summary' => 'Create a registry (customer or supplier)',
        'description' => 'Creates a customer, optionally also a supplier (is_supplier). personal_data is required: type individual (first_name, last_name) or company (company_name), tax code, VAT number, SDI code, contacts (at least one phone) and addresses (line1 and city required); the registry name is derived from it. Tax code, VAT number and phones must be unique across users, registries and referents. manager_slots lists internal account managers by position (one slot per user); referent_ids links referents, primary_referent_id picks the main one. Supervisor and managers are notified. Requires registries.create.',
    ],
    'GET /registries/for-select' => [
        'summary' => 'List registries for a select field',
        'description' => 'Lightweight, paginated option list; label shows the name with VAT number or tax code, meta the qualified-supplier flag, commercial, reporter, supervisor, primary referent and account managers. search matches name, VAT number and tax code; is_supplier=true returns suppliers only; offset/limit paginate; ids[] appends those registries even when outside the page. Only requires authentication.',
    ],
    'GET /registries/{registry}' => [
        'summary' => 'Get a registry',
        'description' => 'Returns the registry with source, sectors, referents and primary referent, account manager slots, supervisor, commercial, reporter, supplier flags, agreement status and notes, size class, employee count and the personal data card with contacts and addresses, plus a permissions block for the caller. Requires registries.view.',
    ],
    'PUT /registries/{registry}' => [
        'summary' => 'Update a registry',
        'description' => 'Partially updates the registry: omitted keys are left untouched; a submitted list (sector_ids, referent_ids, manager_slots) replaces the current one and an empty list clears it. Submitting personal_data updates the card, its contacts and addresses. A newly assigned supervisor or account manager is notified. Returns the registry with a permissions block. Requires registries.update.',
    ],
    'DELETE /registries/{registry}' => [
        'summary' => 'Delete a registry',
        'description' => 'Deletes the registry together with its personal data card, contacts and addresses, and answers 204 with no content. 409 when the registry has leads, opportunities or is the supplier of a product. Requires registries.delete.',
    ],
    'GET /registries/{registry}/compensation-candidates' => [
        'summary' => 'List installments a registry can offset',
        'description' => 'For the required company_id, returns the open installments that can be compensated against each other: receivables (installments of the registry\'s sales invoices) and payables (installments of its purchase invoices), each with amount, due date and the invoice number and date. Open means not collected or paid, not written off and not already compensated. Requires ledger.view.',
    ],
    'GET /registries/{registry}/compensations' => [
        'summary' => 'List the compensations of a registry',
        'description' => 'Returns the compensations registered for the registry, newest first, optionally filtered by company_id: company, date, amount, note, the receivable and payable installments offset against each other, author and can_delete (whether the caller may cancel it). Requires ledger.view.',
    ],
    'GET /registries/{registry}/ledger' => [
        'summary' => 'Get the ledger of a registry',
        'description' => 'Returns the registry\'s ledger: a customer section (sales documents) and a supplier section (purchase documents), each with opening balance, summary, aging and documents with debit, credit, settled and residual amounts, running balance, status and installments. Filters: document_type (invoice default, proforma, all), date_from/date_to, company_id[], company_site_id[], operational_site_id[]. A section is null when the caller lacks invoices.view or purchase-invoices.view, or the registry is not a supplier and has no purchase documents. Requires ledger.view.',
    ],
];
