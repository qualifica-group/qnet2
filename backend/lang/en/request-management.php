<?php

return [
    // Direttiva utente 2026-09-09: a positive close demands the client's
    // fiscal identifier, either one of the two.
    'fiscal_identity_required_for_status' => 'You cannot close the request with a positive outcome without the client tax code or VAT number.',
    // The same rule on the RECORD instead of the transition (decisione utente
    // 2026-09-09): the panel refuses to save a request that SITS in
    // closed_won while the identifier is missing, whatever else it changes.
    'fiscal_identity_required_on_closed_won' => 'The request is closed with a positive outcome: enter the client tax code or VAT number to save it.',
];
