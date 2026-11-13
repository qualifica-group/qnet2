<?php

return [
    'GET /availability' => [
        'summary' => 'Check whether the application is available',
        'description' => 'Public endpoint polled to know whether the application is out of maintenance. Answers 200 with the success envelope when the application is available, 503 with maintenance true when it is under maintenance (users excluded from maintenance get 200). No authentication required.',
    ],
];
