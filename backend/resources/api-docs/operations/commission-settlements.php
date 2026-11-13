<?php

return [
    'GET /commission-settlements/{commissionSettlement}' => [
        'summary' => 'Get a commission settlement',
        'description' => 'Returns the agent commission settlement: status (DRAFT, ISSUED, DOCUMENT_RECEIVED, APPROVED, CANCELLED), period, company and agent, computed amounts (taxable, VAT, withholding, ENASARCO, net payable), the agent document and the linked purchase invoice when present, the attached commission movements, and the transitions the actor can perform now, plus the record and field permissions. Requires commission-settlements.view.',
    ],
    'PATCH /commission-settlements/{commissionSettlement}' => [
        'summary' => 'Update the notes of a draft settlement',
        'description' => 'Replaces the notes of the settlement (notes must be present, null or empty clears them); no other field is writable. 409 when the settlement is not a DRAFT. Returns the updated settlement. Requires commission-settlements.update.',
    ],
    'DELETE /commission-settlements/{commissionSettlement}' => [
        'summary' => 'Delete a draft settlement',
        'description' => 'Deletes a DRAFT settlement and releases its commission movements, which become available to a later settlement. Answers 204; 409 when the settlement is not a DRAFT. Requires commission-settlements.delete.',
    ],
    'GET /commission-settlements/{commissionSettlement}/approval-draft' => [
        'summary' => 'Get the data of the settlement approval form',
        'description' => 'Returns what the approval needs: the supplier registry linked to the agent or, when none is linked, the candidate registries with the same tax code or VAT number as the agent; whether the actor may create a supplier; the payment method, cost type and VAT rate defaulted from the agent fiscal profile; and the description of the purchase invoice line. 409 when the agent document has not been received. Requires commission-settlements.approve.',
    ],
    'POST /commission-settlements/{commissionSettlement}/approve' => [
        'summary' => 'Approve a settlement and create its purchase invoice',
        'description' => 'Approves a settlement in DOCUMENT_RECEIVED: creates a purchase invoice from the agent document (number, date, taxable amount, withholding and ENASARCO) with the given payment_method_id and cost_type_id (both active), vat_rate_id and optional financial_account_id, links it and moves the settlement to APPROVED. 409 in any other status; 422 when the agent has no supplier registry or the computed VAT differs from the document beyond the tolerance. Returns the settlement. Requires commission-settlements.approve and purchase-invoices.create.',
    ],
    'POST /commission-settlements/{commissionSettlement}/cancel' => [
        'summary' => 'Cancel an issued settlement',
        'description' => 'Cancels an ISSUED settlement with a mandatory reason (max 500 characters): it keeps its number, is marked CANCELLED and releases its commission movements. 409 when the settlement is not ISSUED. Returns the settlement. Requires commission-settlements.cancel.',
    ],
    'POST /commission-settlements/{commissionSettlement}/document' => [
        'summary' => 'Register the agent document of a settlement',
        'description' => 'Records the invoice or fee note the agent sent for an ISSUED settlement: document_number, document_date (not in the future) and document_taxable_amount, document_vat_amount, document_withholding_amount, document_enasarco_amount, document_total_amount, then moves it to DOCUMENT_RECEIVED; the settlement amounts are not changed. 409 when not ISSUED; 422 when the total is not taxable plus VAT or an amount differs from the settlement beyond the tolerance. Returns the settlement. Requires commission-settlements.receiveDocument.',
    ],
    'DELETE /commission-settlements/{commissionSettlement}/document' => [
        'summary' => 'Remove the agent document of a settlement',
        'description' => 'Clears the registered agent document and moves the settlement back from DOCUMENT_RECEIVED to ISSUED. 409 when the settlement is not in DOCUMENT_RECEIVED. Returns the settlement. Requires commission-settlements.receiveDocument.',
    ],
    'POST /commission-settlements/{commissionSettlement}/issue' => [
        'summary' => 'Issue a draft settlement',
        'description' => 'Recalculates the DRAFT settlement one last time, then freezes it as ISSUED with a progressive number per company and year of the period end. 409 when not a DRAFT or when the taxable amount is not positive; 422 when the recalculation cannot run (reason missing_profile or missing_enasarco_parameters). Returns the settlement. Requires commission-settlements.issue.',
    ],
    'POST /commission-settlements/{commissionSettlement}/recalculate' => [
        'summary' => 'Recalculate a draft settlement',
        'description' => 'Attaches the commission movements of the agent and company that became open up to the period end, then recomputes every amount from the attached movements with the current fiscal profile and parameters. 409 when not a DRAFT; 422 with the reason code (missing_profile, missing_enasarco_parameters) when the settlement cannot be computed. Returns the settlement. Requires commission-settlements.update.',
    ],
    'POST /commission-settlements/{commissionSettlement}/supplier' => [
        'summary' => 'Link the agent supplier registry',
        'description' => 'Sets the supplier registry the agent is paid through: registry_id links an existing supplier registry, null creates one from the agent card (also requires registries.create). 409 when the agent document has not been received; 422 when the registry is not a supplier, the agent card is incomplete, or the agent already has approved settlements with a different supplier. Returns the linked supplier id and name. Requires commission-settlements.approve.',
    ],
];
