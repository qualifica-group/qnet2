<?php

/**
 * System Health check strings (spec 0187). Safe messages only: no
 * exception detail / internal class name / secret value.
 */
return [

    'ok' => 'System status retrieved.',

    'check_failed' => 'Check unavailable.',

    'database' => [
        'unreachable' => 'Database unreachable.',
    ],

    'email' => [
        'transport_unavailable' => 'Email transport unavailable.',
    ],

    'queue' => [
        'unreachable' => 'Queue tables unreachable.',
    ],

];
