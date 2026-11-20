<?php

return [
    'POST /commission-adjustments' => [
        'summary' => 'Create a manual commission adjustment',
        'description' => 'Posts a manual adjustment movement (positive or negative amount, never zero, two decimals) on an agent\'s commission ledger: referent_id is the agent, company_id the company, accrued_on the accrual date (Y-m-d), notes the mandatory reason. Optional commission_settlement_id attaches it to a draft settlement of the same agent and company, whose totals are recalculated (422 otherwise). The change is written to the activity log. Returns the adjustment. Requires commission-settlements.adjust.',
    ],
    'PUT /commission-adjustments/{commissionAccrual}' => [
        'summary' => 'Update a manual commission adjustment',
        'description' => 'Replaces amount, accrued_on and notes of a manual adjustment; agent, company and settlement cannot change. If it belongs to a draft settlement, that settlement is recalculated. 404 when the movement is not a manual adjustment; 409 when its settlement is no longer a draft. Logged in the activity log. Returns the adjustment. Requires commission-settlements.adjust.',
    ],
    'DELETE /commission-adjustments/{commissionAccrual}' => [
        'summary' => 'Delete a manual commission adjustment',
        'description' => 'Removes a manual adjustment from the agent\'s ledger and recalculates its draft settlement, if any; answers 204 with no content. 404 when the movement is not a manual adjustment; 409 when its settlement is no longer a draft. Logged in the activity log. Requires commission-settlements.adjust.',
    ],
];
