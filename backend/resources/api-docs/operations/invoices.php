<?php

return [
    'GET /invoices/for-select' => [
        'summary' => 'Search invoices for a selector',
        'description' => 'Returns a paginated list of invoices and proformas, newest document date first, each as id, label (number/year) and subtitle (customer and document date). search matches number/year, external number or customer name; offset and limit (default 25, max 100); ids[] appends already selected invoices; include_total false skips the total count and reports whether more rows exist. Any authenticated user.',
    ],
    'POST /invoices/installment-preview' => [
        'summary' => 'Preview the installment schedule of an invoice',
        'description' => 'Computes, without saving, the installments that document_date, payment_method_id and the net, VAT and total amounts would produce. With invoice_id of an invoice that already has collected installments, returns instead the rebalanced plan for the new total, with collected rows marked locked. Each row has sequence, due date, amount, payment method code, collected amount and locked. Requires invoices.update on the given invoice, otherwise invoices.create or invoices.update.',
    ],
    'GET /invoices/monthly-summary' => [
        'summary' => 'Get the monthly invoice totals of a year',
        'description' => 'Returns, for the required year, the twelve months with the number of documents and their total amount by document date. type narrows to proforma or invoice (default all). Requires invoices.viewAny.',
    ],
    'GET /invoices/{invoice}' => [
        'summary' => 'Get an invoice',
        'description' => 'Returns the invoice or proforma with its issuing company, customer, payment method, bank account, lines with VAT rate and revenue type, installments with their collections, tag, layout and amounts, plus the user\'s permissions on it. Requires invoices.view.',
    ],
    'PUT /invoices/{invoice}' => [
        'summary' => 'Update an invoice',
        'description' => 'Fully rewrites an unregistered invoice: header and lines are replaced and amounts recomputed server side (the total must be above zero); the issuing company cannot change. Without collections the installment schedule is regenerated; with collections the document date, payment method and customer are frozen (422) and the open installments are rebalanced to the new total. Commissions are realigned. 409 on a registered invoice. Returns the invoice with permissions. Requires invoices.update.',
    ],
    'DELETE /invoices/{invoice}' => [
        'summary' => 'Delete an invoice',
        'description' => 'Deletes the invoice and puts its proforma request back to pending so it can be invoiced again; the number is not reused. Answers 204 with no content. 409 when the invoice is registered or has collected installments. Requires invoices.delete.',
    ],
    'PATCH /invoices/{invoice}/details' => [
        'summary' => 'Update the registration details of an invoice',
        'description' => 'Updates the submitted external_number and external_date (required together), tag, deviation, internal note and layout. Setting an external number turns a proforma into a registered invoice; clearing it turns it back into a proforma. Changing the external number or date of a registered invoice needs invoices.downgrade (403 otherwise). Returns the invoice with permissions. Requires invoices.update.',
    ],
    'GET /invoices/{invoice}/emails' => [
        'summary' => 'List the emails of an invoice',
        'description' => 'Returns the emails written for the invoice, most recently updated first, 20 per page (page parameter), with meta current_page, last_page and total. Drafts are visible only to their author. Requires invoices.viewEmails.',
    ],
    'POST /invoices/{invoice}/emails' => [
        'summary' => 'Create an email draft for an invoice',
        'description' => 'Creates a draft authored by the user; every field is optional (to, cc, bcc, from_address among the user\'s allowed senders, email_template_id, subject, HTML body, which is sanitized). The invoice PDF is generated and attached unless attach_pdf is false; 422 when no invoice layout is available, and no draft is left. Returns 201 with the email. Requires invoices.sendEmail.',
    ],
    'GET /invoices/{invoice}/emails/compose-context' => [
        'summary' => 'Get the data to compose an invoice email',
        'description' => 'Returns what the composer needs: the sender and allowed sender addresses, recipient suggestions from the customer and its referents, the default recipient (customer primary PEC, else primary email), the customer documents that can be attached, the import sources (invoice_pdf, documents) and the total attachment size limit in KB. Requires invoices.sendEmail.',
    ],
    'POST /invoices/{invoice}/emails/reminder' => [
        'summary' => 'Create a payment reminder draft for an invoice',
        'description' => 'Creates a reminder draft addressed to the customer default recipient, with the invoice PDF attached; with email_template_id (an active template of the invoices module, 422 otherwise) subject and body are rendered from it. 409 when the invoice has no overdue installment. Returns 201 with the email; nothing is sent. Requires invoices.sendEmail.',
    ],
    'POST /invoices/{invoice}/emails/render-template' => [
        'summary' => 'Render an email template for an invoice',
        'description' => 'Returns the subject and body of the email_template_id template with the invoice variables filled in, without saving anything. 422 when the template is inactive or not of the invoices module. Requires invoices.sendEmail.',
    ],
    'GET /invoices/{invoice}/emails/{email}' => [
        'summary' => 'Get an email of an invoice',
        'description' => 'Returns the email with recipients, subject, body, status, sender and attachments. 404 when it belongs to another invoice or is a draft of another user. Requires invoices.viewEmails.',
    ],
    'PUT /invoices/{invoice}/emails/{email}' => [
        'summary' => 'Update an invoice email draft',
        'description' => 'Updates the submitted fields of the user\'s own draft (same fields as creation, body sanitized). 409 when the email is no longer a draft; 404 when not visible. Returns the email. Requires invoices.sendEmail.',
    ],
    'DELETE /invoices/{invoice}/emails/{email}' => [
        'summary' => 'Delete an invoice email draft',
        'description' => 'Deletes the user\'s own draft and its attachments, answering 204 with no content. 409 when the email is no longer a draft; 404 when not visible. Requires invoices.sendEmail.',
    ],
    'POST /invoices/{invoice}/emails/{email}/attachments' => [
        'summary' => 'Upload an attachment to an invoice email draft',
        'description' => 'Uploads one file (multipart field file, size and type limited by the attachment allow-list) onto the draft. 409 when the email is not a draft; 422 when the total attachment size would exceed the limit. Returns 201 with the updated email. Requires invoices.sendEmail.',
    ],
    'POST /invoices/{invoice}/emails/{email}/attachments/import' => [
        'summary' => 'Import attachments into an invoice email draft',
        'description' => 'Copies files onto the draft from a source: invoice_pdf generates the invoice PDF (optional layout_id of an active invoices layout, 422 otherwise) and needs invoices.view; documents copies the attachment_ids taken from the customer registry documents, 422 when any is not one of them. 409 when the email is not a draft. Returns 201 with the updated email. Requires invoices.sendEmail.',
    ],
    'DELETE /invoices/{invoice}/emails/{email}/attachments/{attachment}' => [
        'summary' => 'Remove an attachment from an invoice email draft',
        'description' => 'Deletes one attachment of the draft and returns the updated email. 409 when the email is not a draft; 404 when the attachment is not on this email. Requires invoices.sendEmail.',
    ],
    'GET /invoices/{invoice}/emails/{email}/attachments/{attachment}/download' => [
        'summary' => 'Download an attachment of an invoice email',
        'description' => 'Downloads the attachment file with its original name, for drafts and sent emails alike. 404 when the email or attachment is not visible or the file no longer exists. Requires invoices.viewEmails.',
    ],
    'POST /invoices/{invoice}/emails/{email}/send' => [
        'summary' => 'Send an invoice email',
        'description' => 'Queues a draft or failed email for delivery from the chosen sender address (else the user\'s login email) and answers 202 with the email in queued status; delivery happens in the background. Only its author may send it (403). 409 when the email is not a draft or failed; 422 when recipients, subject or body are missing, the sender is not allowed or attachments exceed the size limit. Requires invoices.sendEmail.',
    ],
    'PATCH /invoices/{invoice}/lines/{line}/revenue-type' => [
        'summary' => 'Set the revenue type of an invoice line',
        'description' => 'Sets or clears (null) the revenue sub-account of one line, on any invoice state, including registered or collected invoices; amounts are untouched. The revenue type must be active unless already on the line. The change is recorded in the invoice activity log. Returns the line id, revenue_type_id and revenue type reference. 404 when the line belongs to another invoice. Requires invoices.update.',
    ],
    'GET /invoices/{invoice}/pdf' => [
        'summary' => 'Download the invoice PDF',
        'description' => 'Generates the invoice or proforma PDF on the fly and returns it as a file download named type_number_year.pdf; nothing is stored. layout_id picks an active invoices layout, else the invoice layout or the default one is used. 422 when no layout is available or the layout is misconfigured. Requires invoices.view.',
    ],
];
