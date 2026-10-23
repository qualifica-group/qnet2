<?php

return [
    // Seconds a rule-driven recalculation job may run (spec 0308, D-7). Must
    // stay below `queue.connections.database.retry_after`.
    'job_timeout' => (int) env('COMMISSION_RECALCULATION_JOB_TIMEOUT', 1800),
];
