<?php

return [

    // Microsoft Graph transport errors (spec 0175, D-1/D-6): thrown by
    // App\Services\Graph\GraphMailException, built from a SAFE, static
    // vocabulary only -- never the raw Graph response body nor the access
    // token (see App\Services\Graph\GraphMailClient).
    'graph_invalid_sender' => 'The sender mailbox :email is not a valid Microsoft 365 mailbox.',
    'graph_auth_error' => 'Invalid Microsoft Graph configuration: check the tenant, client and secret.',
    'graph_generic_error' => 'Microsoft Graph error (:code): the send failed.',

    // SendOutboundEmailJob (D-12): fallback error_message for a mailer
    // failure that is not a GraphMailException, and for a queue worker that
    // kills the job before its own try/catch runs (failed()).
    'job_generic_failure' => 'The send failed: please try again later.',

    // BE-05: work-orders/{workOrder}/emails/* write endpoints (spec 0175,
    // D-2/D-5/D-7/D-12). 409 body/errors on a status conflict; 422 field
    // errors on send/import.
    'not_draft' => 'This email is no longer a draft: it can no longer be edited.',
    'not_author' => 'Only this email\'s author can send it.',
    'not_sendable_status' => 'This email cannot be sent from its current status.',
    'no_recipients' => 'Add at least one recipient in "To".',
    'empty_subject' => 'The subject is required to send the email.',
    'empty_body' => 'The email body is required to send.',
    'sender_email_missing' => 'Your user has no email address: cannot send.',
    'too_many_recipients' => 'You can list at most :max recipients in total (To + Cc + Bcc).',
    'attachments_limit_exceeded' => 'The total attachments size exceeds the :max_kb KB limit.',
    'template_not_available' => 'The selected template is not available for this module.',
    'attachment_not_available' => 'One or more selected files are not available for this commessa.',
    'document_bundle_not_available' => 'The selected document bundle is not available.',

    // WorkOrderEmailComposeContextBuilder (D-5): suggestion label for the
    // commessa's own supervisors/participants.
    'recipient_supervisor' => 'Supervisor',
    'recipient_participant' => 'Participant',

];
