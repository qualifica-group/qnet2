<?php

return [
    'PATCH /rewards/{reward}' => [
        'summary' => 'Change the status of a reward',
        'description' => 'Sets the status of a reward (voucher or incentive assigned to a referent): reward_status_id must be an active reward status. Only the status changes. Returns the reward with its type, status, origin (opportunity or quote) and related context. Requires rewarded-referents.update.',
    ],
];
