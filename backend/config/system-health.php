<?php

return [

    // Spec 0187: a person counts as "online" when one of their API tokens was
    // used (the frontend heartbeat touches last_used_at) within this window.
    'online_window_minutes' => (int) env('SYSTEM_HEALTH_ONLINE_WINDOW_MINUTES', 2),

    // Queue check: more pending jobs than this degrades the check.
    'queue_pending_threshold' => (int) env('SYSTEM_HEALTH_QUEUE_PENDING_THRESHOLD', 100),

];
