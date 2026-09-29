<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mailer
    |--------------------------------------------------------------------------
    |
    | The Laravel mailer (config/mail.php `mailers`) SendOutboundEmailJob uses
    | to actually deliver an OutboundEmail (spec 0175, D-1): the custom
    | `microsoft-graph` transport in production/staging, `log` in local dev so
    | no real mailbox is needed to exercise the composer. Passing through
    | `Mail::mailer()` (rather than hard-coding the Graph transport) keeps
    | `Mail::fake()` and `StagingMailRedirector`'s `MAIL_ALWAYS_TO` working
    | unchanged.
    |
    */

    'mailer' => env('OUTBOUND_EMAIL_MAILER', 'microsoft-graph'),

    /*
    |--------------------------------------------------------------------------
    | Maximum total attachments size (kilobytes)
    |--------------------------------------------------------------------------
    |
    | Upper bound on the SUM of an OutboundEmail's own allegati (D-7), checked
    | on every attachment upload/import and again at send. Default 25600 = 25
    | MB.
    |
    */

    'max_total_attachments_kb' => (int) env('OUTBOUND_EMAILS_MAX_TOTAL_ATTACHMENTS_KB', 25600),

    /*
    |--------------------------------------------------------------------------
    | Maximum recipients
    |--------------------------------------------------------------------------
    |
    | Upper bound on the combined count of to/cc/bcc addresses on one
    | OutboundEmail (D-5).
    |
    */

    'max_recipients' => (int) env('OUTBOUND_EMAILS_MAX_RECIPIENTS', 50),

];
