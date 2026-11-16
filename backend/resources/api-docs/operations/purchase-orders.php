<?php

return [
    'POST /purchase-orders' => [
        'summary' => 'Create a purchase order',
        'description' => 'Creates a draft purchase order numbered per company and year (code number/year). Required: supplier_id (a supplier registry), company_id, company_site_id (of that company), order_date, description and 1 to 200 lines with description, cost_type_id, quantity > 0 and unit_price (optional product, unit of measure, VAT rate, work order); totals are computed server-side. copies (1 to 12) creates that many independent monthly copies, each dated one month after the previous. Returns 201 with the first order, permissions and meta.created_ids (every created order). Requires purchase-orders.create.',
    ],
    'GET /purchase-orders/for-select' => [
        'summary' => 'List purchase orders for a dropdown',
        'description' => 'Returns purchase orders as id, label (code number/year) and subtitle (supplier and order date), most recent order date first. search matches the number or number/year, the description or the supplier name; ids[] also returns those orders for display of a saved value. Paginated with offset and limit; include_total=false skips the count and reports has_more instead. Response is a list (items + pagination). Requires authentication only.',
    ],
    'GET /purchase-orders/{purchaseOrder}' => [
        'summary' => 'Get a purchase order',
        'description' => 'Returns the purchase order with supplier, company, sites, references (customer, work order, quote, opportunity, sectors), totals, lines with received, invoiced and residual quantities and fulfillment, linked purchase invoices and merge count, plus abilities (update, delete, merge, send, receive and the status transitions the caller may perform), field permissions and the caller\'s permissions block. Requires purchase-orders.view.',
    ],
    'PUT /purchase-orders/{purchaseOrder}' => [
        'summary' => 'Update a purchase order',
        'description' => 'Partially updates the order with the create fields (copies excluded); lines, when sent, replace the whole set (kept by id, missing ones deleted and their purchase request lines released). While draft or in validation everything but the company may change (422 on company_id); from approval on only the service fields (operational site, notes, payment method, internal user flag, customer, work order, quote, opportunity, sectors), 409 otherwise and when cancelled. Returns the order with permissions. Requires purchase-orders.update.',
    ],
    'DELETE /purchase-orders/{purchaseOrder}' => [
        'summary' => 'Delete a purchase order',
        'description' => 'Deletes the order with its lines, releasing the purchase request lines they were linked to. 409 when the order is no longer draft or in validation, or is already linked to a purchase invoice. Returns 200 with empty data. Requires purchase-orders.delete.',
    ],
    'GET /purchase-orders/{purchaseOrder}/emails' => [
        'summary' => 'List the emails of a purchase order',
        'description' => 'Returns the outbound emails of the order, most recently updated first, 20 per page (query page), with meta current_page, last_page and total. Drafts are visible only to their author. Requires purchase-orders.view.',
    ],
    'POST /purchase-orders/{purchaseOrder}/emails' => [
        'summary' => 'Create an email draft for a purchase order',
        'description' => 'Creates an email draft owned by the caller, optionally prefilled with to, cc, bcc, from_address (one of the caller\'s allowed sender addresses), email_template_id, subject and body (HTML). The order PDF is generated and attached unless attach_pdf is false. 409 unless the order is approved or already sent to the supplier; 422 when the PDF cannot be generated (no active purchase order layout), in which case no draft is left. Returns 201 with the email. Requires purchase-orders.send.',
    ],
    'GET /purchase-orders/{purchaseOrder}/emails/compose-context' => [
        'summary' => 'Get the email composer context of a purchase order',
        'description' => 'Returns what the composer needs: the caller as sender and the allowed sender_options, recipient_suggestions (supplier and its referents\' email addresses, when the caller can view them), default_to (the supplier\'s primary email, else PEC, when present), the supplier\'s documents that can be attached, the import sources (purchase_order_pdf, documents) and max_total_attachments_kb. Requires purchase-orders.send.',
    ],
    'POST /purchase-orders/{purchaseOrder}/emails/render-template' => [
        'summary' => 'Render an email template for a purchase order',
        'description' => 'Fills the subject and body of the email template email_template_id with the order\'s data and returns them as subject and body, without saving anything. 422 when the template is inactive or not a purchase order template. Requires purchase-orders.send.',
    ],
    'GET /purchase-orders/{purchaseOrder}/emails/{email}' => [
        'summary' => 'Get an email of a purchase order',
        'description' => 'Returns the email with status (draft, queued, sent, failed...), sender, recipients, subject, body, attachments and their total size, timestamps, error message and can (update, delete, send). 404 when the email belongs to another owner or is someone else\'s draft. Requires purchase-orders.view.',
    ],
    'PUT /purchase-orders/{purchaseOrder}/emails/{email}' => [
        'summary' => 'Update an email draft of a purchase order',
        'description' => 'Updates the caller\'s own draft: to, cc, bcc, from_address, email_template_id, subject and body. 409 when the email is no longer a draft; 404 when it is not visible to the caller. Returns the email. Requires purchase-orders.send.',
    ],
    'DELETE /purchase-orders/{purchaseOrder}/emails/{email}' => [
        'summary' => 'Delete an email draft of a purchase order',
        'description' => 'Deletes the caller\'s own draft and its attachments. 409 when the email is no longer a draft; 404 when it is not visible to the caller. Returns 204 no content. Requires purchase-orders.send.',
    ],
    'POST /purchase-orders/{purchaseOrder}/emails/{email}/attachments' => [
        'summary' => 'Upload an attachment to a purchase order email draft',
        'description' => 'Multipart upload of a file (field file, allowed types and size limit apply) to the caller\'s draft. 409 when the email is not a draft; 422 when the total attachments size would exceed the limit. Returns 201 with the updated email. Requires purchase-orders.send.',
    ],
    'POST /purchase-orders/{purchaseOrder}/emails/{email}/attachments/import' => [
        'summary' => 'Attach the order PDF or supplier documents to an email draft',
        'description' => 'Copies files onto the caller\'s draft. source=purchase_order_pdf generates the order PDF (optional layout_id of an active purchase order layout, otherwise the default one); source=documents copies the supplier\'s documents given in attachment_ids (requires the right to view registry documents). 409 when the email is not a draft; 422 when a layout or document is not available or the size limit is exceeded. Returns 201 with the updated email. Requires purchase-orders.send.',
    ],
    'DELETE /purchase-orders/{purchaseOrder}/emails/{email}/attachments/{attachment}' => [
        'summary' => 'Remove an attachment from a purchase order email draft',
        'description' => 'Removes one attachment from the caller\'s draft. 409 when the email is not a draft; 404 when the attachment does not belong to the email. Returns the updated email. Requires purchase-orders.send.',
    ],
    'GET /purchase-orders/{purchaseOrder}/emails/{email}/attachments/{attachment}/download' => [
        'summary' => 'Download an attachment of a purchase order email',
        'description' => 'Downloads one attachment of a visible email of the order as a file, drafts included only for their author. 404 when the attachment does not belong to the email or its file no longer exists. Requires purchase-orders.view.',
    ],
    'POST /purchase-orders/{purchaseOrder}/emails/{email}/send' => [
        'summary' => 'Send a purchase order email',
        'description' => 'Queues the caller\'s draft, or resends their failed email, for asynchronous delivery and returns 202 with the email in queued status; follow its status through the email endpoints. 409 unless the order is approved or sent to the supplier, or when the email is not draft or failed; 403 when the caller is not its author; 422 when recipients, subject or body are missing, the sender address is not allowed, or attachments exceed the size limit. Requires purchase-orders.send.',
    ],
    'POST /purchase-orders/{purchaseOrder}/lines/{line}/close-residual' => [
        'summary' => 'Close the residual quantity of a purchase order line',
        'description' => 'Declares that the quantity still missing on the line will not be delivered, with a required reason: the line then counts as complete and can be billed only up to the received quantity; the order status and linked purchase request lines follow. 409 when the order is not approved, sent or partially fulfilled, the line is already complete, or it is billed beyond what was received; 404 when the line belongs to another order. Returns the updated order with permissions. Requires purchase-orders.receive.',
    ],
    'DELETE /purchase-orders/{purchaseOrder}/lines/{line}/close-residual' => [
        'summary' => 'Reopen the residual quantity of a purchase order line',
        'description' => 'Undoes a residual closure, so the missing quantity is expected again; the order status and linked purchase request lines follow. 409 when the line residual is not closed or the order is cancelled; 404 when the line belongs to another order. Returns the updated order with permissions. Requires purchase-orders.receive.',
    ],
    'POST /purchase-orders/{purchaseOrder}/merge' => [
        'summary' => 'Merge lines of other orders into a purchase order',
        'description' => 'Moves the lines line_ids of other orders of the same supplier and company to the end of this order, keeping their ids; totals are recomputed, a source left without lines is deleted and each source leaves a merge log. All or nothing. 409 when any order involved is not draft or in validation or an emptied source cannot be deleted; 422 when a line already belongs here, comes from another supplier or company, or is no longer available; 403 without update (and delete for emptied sources) on the sources. Returns the order and meta.deleted_purchase_order_ids. Requires purchase-orders.merge.',
    ],
    'GET /purchase-orders/{purchaseOrder}/merge-candidates' => [
        'summary' => 'List the orders that can be merged into a purchase order',
        'description' => 'Returns the other orders of the same supplier and company still draft or in validation, most recent first, each with code, date, description, status, total and lines, to choose line_ids for the merge. 409 when this order is not draft or in validation. Requires purchase-orders.merge.',
    ],
    'GET /purchase-orders/{purchaseOrder}/merge-logs' => [
        'summary' => 'List the merges into a purchase order',
        'description' => 'Returns the merges where this order was the destination, most recent first: source order id and code, what happened to the source, its description, date and total before the merge, the moved lines, who performed it and when. Requires purchase-orders.view.',
    ],
    'GET /purchase-orders/{purchaseOrder}/pdf' => [
        'summary' => 'Download the purchase order PDF',
        'description' => 'Generates the order PDF with the default active purchase order document layout and returns it as a file download (ODA-number-year.pdf); nothing is stored. 422 when no purchase order layout is available or its configuration is invalid. Requires purchase-orders.view.',
    ],
    'GET /purchase-orders/{purchaseOrder}/receipts' => [
        'summary' => 'List the deliveries of a purchase order',
        'description' => 'Returns the goods receipts recorded on the order, most recent receipt date first: date, delivery note number and date, notes, author, the purchase invoice that generated it if any, the received lines with quantities, and abilities.delete. Requires purchase-orders.view.',
    ],
    'POST /purchase-orders/{purchaseOrder}/receipts' => [
        'summary' => 'Record a delivery on a purchase order',
        'description' => 'Records a goods receipt: receipt_date (not in the future), optional delivery note number and date and notes, and lines with purchase_order_line_id and quantity received (up to 3 decimals). Received quantities are added to the lines, and the order status (partially fulfilled, fulfilled) and the linked purchase request lines are updated. 409 unless the order is approved, sent or partially fulfilled; 422 per line when it is not receivable (other order or closed residual) or the quantity exceeds the residual. Returns 201 with receipt and the updated order. Requires purchase-orders.receive.',
    ],
    'DELETE /purchase-orders/{purchaseOrder}/receipts/{receipt}' => [
        'summary' => 'Cancel a delivery of a purchase order',
        'description' => 'Removes the receipt and subtracts its quantities from the lines; the order status and linked purchase request lines follow. 409 when the order is cancelled or a line would drop below the quantity already invoiced. Returns the updated order with permissions. Requires purchase-orders.receive.',
    ],
    'POST /purchase-orders/{purchaseOrder}/status' => [
        'summary' => 'Change the status of a purchase order',
        'description' => 'Moves the order to to_status. Allowed: draft to in_validation, approved, cancelled; in_validation to draft, approved, cancelled; approved to in_validation, sent_to_supplier, cancelled; sent_to_supplier to cancelled; cancelled to draft. partially_fulfilled and fulfilled derive from deliveries. Cancelling releases the linked purchase request lines. 422 when the transition is not allowed, or the order has deliveries or closed residuals and would go back or be cancelled. Returns the order with permissions. Requires purchase-orders.view plus purchase-orders.approve to approve, purchase-orders.changeStatus otherwise.',
    ],
];
