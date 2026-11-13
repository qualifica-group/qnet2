<?php

return [
    'GET /work-order-workflow-statuses/for-select' => [
        'summary' => 'List work order workflow statuses for a select',
        'description' => 'Returns a page of the statuses of every active work order workflow plus the default status set (work_order_workflow_id null): default set first, then workflows by name, then each workflow\'s status order. Each item carries id, name, color, progress_percentage, system_key, work_order_workflow_id and work_order_workflow_name, not the usual id/label shape. Optional search matches the status or workflow name; offset and limit (max 100) paginate; ids[] appends those statuses even when outside the page, search or active workflows, for prefilling. Response holds items and pagination. Needs only authentication.',
    ],
];
