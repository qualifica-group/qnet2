<?php

return [
    'POST /commission-settlement-runs' => [
        'summary' => 'Launch a commission settlement run',
        'description' => 'Settles the open commission movements of agents accrued up to period_to: creates one draft settlement per (agent referent, company) pair, attaching its movements. Optional filters: company_id, referent_ids, recipient_roles (COMMERCIAL, REPORTER, SUPERVISOR; manual adjustments are always included) and min_amount (default from configuration). A pair is skipped, its movements staying open, with reason draft_exists, non_positive, below_threshold, missing_profile or missing_enasarco_parameters. Answers 201 with the run, the created settlements and the skipped pairs. Requires commission-settlements.create.',
    ],
    'GET /commission-settlement-runs/{commissionSettlementRun}' => [
        'summary' => 'Get a commission settlement run',
        'description' => 'Returns the run (period, filters, minimum amount, number of settlements, author), the settlements it created with agent, company, taxable amount and net payable, and the skipped pairs with their reason. Amount fields the caller cannot see are omitted. Requires commission-settlements.viewAny.',
    ],
];
