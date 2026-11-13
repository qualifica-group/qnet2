<?php

/*
|--------------------------------------------------------------------------
| API clients (specs 0209, 0210)
|--------------------------------------------------------------------------
|
| External systems call the regular /api endpoints through an API client: its
| key is the Sanctum token of a technical super-admin user, and client-login
| lets them act as a QNet user. Single source of truth for the per-client
| rate limit, the technical users, the login-from-client tokens and the
| generated documentation.
|
*/

return [
    // Path prefix of the documented API (Scramble api_path, OpenAPI server URL).
    'prefix' => 'api',

    'version' => '1.0',

    'rate_limit' => [
        // Requests per minute for a client with no explicit limit.
        'default' => (int) env('EXTERNAL_API_RATE_LIMIT', 60),
        // Upper bound an admin can set on a single client.
        'max' => 1000,
    ],

    'service_users' => [
        // Domain of the synthetic, undeliverable email of each technical user.
        'email_domain' => env('EXTERNAL_API_SERVICE_USER_EMAIL_DOMAIN', 'api-clients.qnet.invalid'),
    ],

    'user_tokens' => [
        // Lifetime, in minutes, of a token issued by POST /api/auth/client-login.
        'ttl_minutes' => (int) env('EXTERNAL_API_USER_TOKEN_TTL_MINUTES', 1440),
    ],

    // Spec 0392: the HTTP methods each API client may use. Route URIs relative to
    // the prefix, exactly as registered.
    'method_gate' => [
        // POST endpoints that only read or compute (lists, previews, rendering):
        // they count as GET, so a read-only client can still list records.
        'read_only_post_routes' => [
            'tables/{domain}/rows',
            'tables/{domain}/values',
            'imports/{domain}/{importRun}/rows',
            'assignment/selection-scope',
            'identity/duplicate-check',
            'absence-requests/preview',
            'document-layouts/{documentLayout}/preview',
            'invoices/installment-preview',
            'purchase-invoices/installment-preview',
            'invoices/{invoice}/emails/render-template',
            'purchase-orders/{purchaseOrder}/emails/render-template',
            'work-orders/{workOrder}/emails/render-template',
            'quotes/form-context',
            'request-management/form-context',
            'work-orders/form-context',
        ],

        // Always reachable whatever the client allows: logging users in and
        // revoking tokens must keep working for a client without POST.
        'always_allowed_routes' => [
            'auth/client-login',
            'auth/logout',
        ],
    ],

    'docs' => [
        // Route paths (relative to the prefix) that only the QNet app uses and so are
        // left out of the generated documentation. An entry also covers its sub-paths;
        // `*` matches one or more segments. auth/logout stays documented: it revokes
        // the token returned by client-login.
        'excluded_prefixes' => [
            'migrations',
            'users/*/impersonate',
            'auth/impersonation',
            'auth/stop-impersonation',
            'presence',
            'auth/login',
            'auth/refresh',
            'auth/me',
            'auth/forgot-password',
            'auth/reset-password',
            'config',
            'system-health',
            // Spec 0359: Echo's private-channel auth for the in-app realtime chat.
            'broadcasting',
        ],
    ],
];
