<?php

return [
    'POST /commission-configurations' => [
        'summary' => 'Create a commission rule',
        'description' => 'Creates a commission rule paid to a recipient_role (COMMERCIAL, REPORTER, SUPERVISOR or SUPPLIER) as a FIXED_AMOUNT or PERCENTAGE value, valid from valid_from to the optional valid_until, with priority and status ACTIVE or SUSPENDED. application_scope: PRODUCT_CATEGORY needs an active product_category_id (whole branch), PRODUCT an active product_id, RECIPIENT a recipient_id. recipient_type (referent or user for people, registry for SUPPLIER) defaults from the role; SUPPLIER also needs supplier_commission_direction (RECEIVED or PAID). Answers 201. Requires commission-configurations.create.',
    ],
    'GET /commission-configurations/{commissionConfiguration}' => [
        'summary' => 'Get a commission rule',
        'description' => 'Returns the commission rule with its product category, product and recipient, plus the caller\'s resource, field and action permissions on it. Requires commission-configurations.view.',
    ],
    'PUT /commission-configurations/{commissionConfiguration}' => [
        'summary' => 'Update a commission rule',
        'description' => 'Partially updates a commission rule: only the fields sent change, with the same rules as creation checked against the merged values. 422 when a role change would leave the saved recipient on a type the new role does not admit (resend recipient_id or clear it with null), or when a field the caller cannot edit is sent. Saving does not touch existing quotes: use the recalculate-quotes endpoint for that. Requires commission-configurations.update.',
    ],
    'DELETE /commission-configurations/{commissionConfiguration}' => [
        'summary' => 'Delete a commission rule',
        'description' => 'Deletes a commission rule. Answers 204 with no content; 409 when the rule has already been applied to commissions. Requires commission-configurations.delete.',
    ],
    'GET /commission-configurations/{commissionConfiguration}/quote-impact' => [
        'summary' => 'Preview the open quotes a rule would realign',
        'description' => 'Counts what a recalculation of this rule would touch: the open (not closed won or lost) quotes and their revenue lines carrying a rule-driven commission of it (quotes_count, lines_count), and how many manual commissions sit on those lines (manual_commissions_count). can_apply tells whether the caller may run the recalculation. Requires commission-configurations.view.',
    ],
    'POST /commission-configurations/{commissionConfiguration}/recalculate-quotes' => [
        'summary' => 'Recalculate the open quotes that use a rule',
        'description' => 'Queues the propagation of the saved rule to the open quotes whose lines carry a rule-driven commission of it. mode is KEEP_MANUAL (refresh only the rule-driven commissions) or REGENERATE_ALL (rebuild every commission of those lines from the current rules). Answers 202 with quotes_count; each quote is recalculated in its own transaction and the caller is notified with the updated and failed counts. Requires commission-configurations.update and quotes.update.',
    ],
];
