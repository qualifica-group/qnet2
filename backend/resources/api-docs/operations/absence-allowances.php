<?php

return [
    'PUT /absence-allowances' => [
        'summary' => 'Set the yearly absence allowance of a person',
        'description' => 'Creates or updates the allowance of one person for one absence type and year (one record per person, type and year). The type must deduct a balance, otherwise 422. A null annual_minutes falls back to the type default in days times the person\'s daily minutes; carried_over_minutes may be negative. Returns the recalculated balance row (annual, source, carry-over, accrued, used, pending and residual minutes). Requires absence-requests.manageAll.',
    ],
    'PUT /absence-allowances/bulk' => [
        'summary' => 'Set the yearly absence allowance of many people',
        'description' => 'Sets the allowance of up to 500 people for one absence type and year from a number of days: each person receives annual_days times their own daily working minutes, so part-timers get proportionally less. A null annual_days returns everyone to the type default. Existing carry-overs are kept; new records start at 0. The type must deduct a balance, otherwise 422. Returns the number of allowances written. Requires absence-requests.manageAll.',
    ],
];
