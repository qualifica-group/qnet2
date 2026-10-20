<?php

return [
    'no_working_day' => 'The selected period has no working day.',
    'calendar_period_too_long' => 'The calendar period can cover at most :max days.',
    'minutes_out_of_range' => 'The requested minutes must be between 1 and :max.',
    'overlap' => 'The period overlaps another absence request of the same person.',
    'no_approvers' => 'No approver is available for this person.',
    'not_draft' => 'Only a draft request can be changed.',
    'not_submitted' => 'Only a submitted request can be decided.',
    'not_cancellable' => 'Only a submitted or approved request can be cancelled.',
    'already_started' => 'An approved request that has already started can only be cancelled by the HR office.',

    'notification' => [
        'submitted' => [
            'title' => 'Absence request to approve',
            'approver' => ':person submitted an absence request (:type, :period).',
        ],
        'approved' => [
            'title' => 'Absence request approved',
            'person' => 'Your absence request (:type, :period) was approved by :actor.',
            'automatic_person' => 'Your absence request (:type, :period) was recorded: this type needs no approval.',
            'automatic_approver' => ':person recorded an absence (:type, :period) that needs no approval.',
        ],
        'rejected' => [
            'title' => 'Absence request rejected',
            'person' => 'Your absence request (:type, :period) was rejected by :actor.',
        ],
        'cancelled' => [
            'title' => 'Absence request cancelled',
            'person' => ':actor cancelled your absence request (:type, :period).',
            'approver' => ':actor cancelled the absence request of :person (:type, :period).',
        ],
        'reason' => 'Reason: :reason',
    ],
];
