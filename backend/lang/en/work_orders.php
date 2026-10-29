<?php

return [
    'delete_has_accounting_documents' => 'The work order cannot be deleted: it has linked accounting documents (proforma requests, invoices, purchase orders or purchase invoices).',
    'lines_not_offer_revenue' => 'The selected lines must be revenue lines of the selected contracts\' offers.',
    'line_already_programmed' => 'One of the selected lines is already in work order :code: each line can belong to one work order only.',
    'multi_quote' => [
        'contracts_not_won' => 'These contracts are not in a "Closed (positive)" status and cannot be programmed yet: :codes. Remove them from the selection.',
        'contracts_not_allowed' => 'These contracts cannot be grouped into a multi-offer work order: :codes. Their offer has no line of a branch with the "Multi-offer work order" setting on: remove them from the selection.',
        'lines_not_allowed' => 'Every selected line must have a product of a branch with the "Multi-offer work order" setting on.',
        'lines_different_branches' => 'The selected lines belong to different branches: a multi-offer work order groups lines of a single branch.',
        'lines_single_quote' => 'The selected lines come from a single offer: a multi-offer work order needs lines from at least two offers. For a single offer use Program from the contract.',
        'lines_removal_only' => 'On a multi-offer work order you can only remove lines, keeping at least one: you cannot add new ones.',
    ],
    'progress' => [
        'percentage_not_above_current' => 'The total must be greater than the progress reached.',
    ],
    'workflow_status' => [
        'force_close_status_invalid' => 'The closing status must be a "Closed with negative outcome" status of the work order\'s working status set.',
        'progress_not_above_current' => 'The status brings the progress to :percentage%, which does not exceed the progress already reached (:current%).',
    ],
];
