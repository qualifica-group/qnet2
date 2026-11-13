<?php

return [
    'POST /work-orders' => [
        'summary' => 'Create a work order',
        'description' => 'Creates a work order (commessa) for quote_id, type processing or project, with start_date; code is generated (COM-0001...) when omitted. quote_line_ids must be revenue lines of that quote not in another work order for the same period; the title derives from them when not given. Optional: supervisor_ids, participant_slots (user ids, null for an empty slot), work_order_workflow_status_id, an active task_template_id whose tasks are generated, attribute_values, is_force_closed with force_close_reason. New members are notified. Answers 201. Requires work-orders.create.',
    ],
    'GET /work-orders/for-select' => [
        'summary' => 'List work orders for a select',
        'description' => 'Returns a page of the work orders visible to the caller as options: id, label = code plus title, meta with the client registry. Optional search matches code or title; registry_id keeps the work orders of one client; offset and limit (max 100) paginate; include_total=false skips the total count; ids[] appends those work orders (still only visible ones) for prefilling. Response holds items and pagination. Needs only authentication.',
    ],
    'POST /work-orders/form-context' => [
        'summary' => 'Preview the additional information for chosen quote lines',
        'description' => 'Given the quote_line_ids composed so far, returns the additional information attributes that apply to them (applicable_attributes) and their form layout (attribute_layout), so a work order form can render the right fields before saving. Nothing is written. Requires work-orders.create or work-orders.update.',
    ],
    'GET /work-orders/next-code' => [
        'summary' => 'Preview the next work order code',
        'description' => 'Returns the next sequential work order code (e.g. COM-0042) as a non-binding suggestion: the actual code is assigned atomically at creation. Requires work-orders.create.',
    ],
    'GET /work-orders/{workOrder}' => [
        'summary' => 'Get a work order',
        'description' => 'Returns the work order with its quote and contract, quote lines (product, category, client), supervisors, participants, workflow status, task template, client registry and additional information, plus the caller\'s permissions. Requires work-orders.view and the work order within the caller\'s visibility (all, own team, operational site or competence); 403 otherwise.',
    ],
    'PUT /work-orders/{workOrder}' => [
        'summary' => 'Update a work order',
        'description' => 'Partially updates a work order; code, quote_id and task_template_id cannot be sent. quote_line_ids, supervisor_ids and participant_slots replace the whole list when sent. Setting is_force_closed to true (with force_close_reason) force-closes its open tasks and moves it to a closed status. A manually chosen workflow status whose positive progress percentage is not above the progress already reached is refused with 422; a higher one adds a progress entry. Newly added members are notified. Requires work-orders.update on a visible work order.',
    ],
    'DELETE /work-orders/{workOrder}' => [
        'summary' => 'Delete a work order',
        'description' => 'Deletes the work order and its links to quote lines, supervisors and participants; quotes and lines are untouched. Answers 204 with no content; 409 when proforma requests, invoices, purchase orders or purchase invoices reference it. Requires work-orders.delete on a visible work order.',
    ],
    'GET /work-orders/{workOrder}/chat/messages' => [
        'summary' => 'List the chat messages of a work order',
        'description' => 'Returns a page of the team chat of a project work order: messages (oldest first, deleted ones with null body), has_more, can_write, can_moderate, unread_count, last_read_message_id and the realtime channel name. limit defaults to 50 (max 100); before_id loads older messages. 404 when the work order is not of type project; 403 when the caller cannot view it or is not a supervisor, participant or super-admin.',
    ],
    'POST /work-orders/{workOrder}/chat/messages' => [
        'summary' => 'Post a chat message on a work order',
        'description' => 'Posts body (max 2000 characters) to the team chat of a project work order and broadcasts it in realtime. Answers 201 with the message. 409 when the work order is completed or closed; 404 when not a project; 403 outside the team.',
    ],
    'PATCH /work-orders/{workOrder}/chat/messages/{chatMessage}' => [
        'summary' => 'Edit a chat message',
        'description' => 'Replaces the body of a chat message and marks it edited, broadcasting the change. Only the author within the edit window (editable_until, 15 minutes by default) or a super-admin may edit; 403 otherwise. 409 when the work order is completed or closed.',
    ],
    'DELETE /work-orders/{workOrder}/chat/messages/{chatMessage}' => [
        'summary' => 'Delete a chat message',
        'description' => 'Deletes a chat message logically: its body is wiped and it stays in the thread as deleted, and the change is broadcast. Returns the deleted message. Same rules as editing: author within the edit window or super-admin (403 otherwise); 409 when the work order is completed or closed.',
    ],
    'POST /work-orders/{workOrder}/chat/read' => [
        'summary' => 'Mark the work order chat as read',
        'description' => 'Moves the caller\'s read marker of the chat forward to last_message_id (a lower id never moves it back). Returns last_read_message_id and the remaining unread_count. Same access rules as reading the chat.',
    ],
    'GET /work-orders/{workOrder}/contract-data' => [
        'summary' => 'Get the contract data of a work order',
        'description' => 'Returns, from the figures already saved on the offer, the work order\'s lines with product, typology, client, quantity, unit price, net amount, effective revenue, supplier commission, warnings and payment data (status, payment agreement, has_unpaid), plus totals overall and per product typology. Commission figures are null unless the caller may see the offer\'s commissions (commissions_visible). Requires work-orders.viewContractData and work-orders.viewCosts on a visible work order.',
    ],
    'PATCH /work-orders/{workOrder}/contract-data/lines' => [
        'summary' => 'Set the payment data of several work order lines',
        'description' => 'Applies the same payment values to up to 200 lines of the work order (quote_line_ids): work_order_payment_status_id (an active payment status, or null) and/or has_unpaid; only the keys sent change. When lines move to a status that allows delivery, the supervisors and participants (not the caller) get one notification listing them. Returns the whole contract data. Requires work-orders.managePayments and work-orders.viewCosts.',
    ],
    'PATCH /work-orders/{workOrder}/contract-data/lines/{quoteLine}' => [
        'summary' => 'Set the payment data of a work order line',
        'description' => 'Saves the payment data of one line of the work order: work_order_payment_status_id (an active status, or the current one), payment_agreement (text, max 2000) and has_unpaid; only the keys sent change. When the line moves to a status that allows delivery, the supervisors and participants (not the caller) are notified. Returns that line\'s contract data. 404 when the line is not in the work order. Requires work-orders.managePayments and work-orders.viewCosts.',
    ],
    'GET /work-orders/{workOrder}/costs' => [
        'summary' => 'Get the costs of a work order',
        'description' => 'Returns the actual cost lines entered on the work order, the offer\'s cost budget (cost lines allocated to the work order\'s revenue lines, and the unallocated ones shown for information) and the comparison per revenue line and in total: revenue, budget cost, actual cost, delta and margins, including committed purchase order costs and invoiced purchase costs, plus totals by cost type. Amounts are net decimal strings. Requires work-orders.viewCosts on a visible work order.',
    ],
    'PUT /work-orders/{workOrder}/costs' => [
        'summary' => 'Replace the actual costs of a work order',
        'description' => 'Replaces the whole list of actual costs: lines with id are updated, lines without id are created, saved lines missing from the list are deleted, and the array order becomes the display order. Each line needs a cost-usable product_id, quantity, unit_price and incurred_on, with optional vat_rate_id, cost_type_id, quote_line_id (a revenue line of this work order the cost is attributed to), supplier_id (a supplier registry) and document_reference; amounts are computed by the server. Returns the cost overview. Requires work-orders.manageCosts on a visible work order.',
    ],
    'GET /work-orders/{workOrder}/emails' => [
        'summary' => 'List the emails of a work order',
        'description' => 'Returns the work order\'s email history, most recently updated first, 20 per page (query parameter page): status (draft, queued, sent, failed, scheduled, schedule_ended), sender, recipients, subject, attachment count, next recurring send date and sent or failed time. Drafts are visible only to their author. Pagination is in meta (current_page, last_page, total) next to data. Requires work-orders.viewEmails on a visible work order.',
    ],
    'POST /work-orders/{workOrder}/emails' => [
        'summary' => 'Create an email draft on a work order',
        'description' => 'Creates an email draft on the work order authored by the caller. All fields are optional: to, cc, bcc (email address lists), from_address (one of the caller\'s allowed sender addresses), email_template_id, subject and body (HTML, sanitized). Answers 201 with the draft; send it with POST .../send. Requires work-orders.sendEmail on a visible work order.',
    ],
    'GET /work-orders/{workOrder}/emails/compose-context' => [
        'summary' => 'Get the data to compose a work order email',
        'description' => 'Returns what the email composer needs: the caller as default sender and the allowed sender addresses, recipient suggestions (client registry and referent contacts when the caller may see them, supervisors, participants), the documents of the work order and of its client that can be attached, whether the quote PDF can be attached, and the total attachment size limit in KB. Requires work-orders.sendEmail on a visible work order.',
    ],
    'POST /work-orders/{workOrder}/emails/render-template' => [
        'summary' => 'Render an email template for a work order',
        'description' => 'Fills the variables of the email template email_template_id with the work order\'s data (quote, client, opportunity, referent, people, company and site) and returns the resulting subject and body; nothing is saved. 422 when the template is missing, inactive or not a work order template. Requires work-orders.sendEmail on a visible work order.',
    ],
    'GET /work-orders/{workOrder}/emails/{email}' => [
        'summary' => 'Get a work order email',
        'description' => 'Returns one email of the work order with sender, recipients, subject, body, status, recurrence and attachments. 404 when it belongs to another work order or is someone else\'s draft. Requires work-orders.viewEmails on a visible work order.',
    ],
    'PUT /work-orders/{workOrder}/emails/{email}' => [
        'summary' => 'Update a work order email draft',
        'description' => 'Changes the sent fields of the caller\'s own draft (to, cc, bcc, from_address, email_template_id, subject, body). 409 when the email is no longer a draft; 404 for someone else\'s draft. Requires work-orders.sendEmail on a visible work order.',
    ],
    'DELETE /work-orders/{workOrder}/emails/{email}' => [
        'summary' => 'Delete a work order email draft',
        'description' => 'Deletes the caller\'s own draft with its attachments. Answers 204 with no content; 409 when the email is no longer a draft. Requires work-orders.sendEmail on a visible work order.',
    ],
    'POST /work-orders/{workOrder}/emails/{email}/attachments' => [
        'summary' => 'Upload an attachment to a work order email draft',
        'description' => 'Uploads file (multipart, allowed types and size only) as an attachment of the draft. Answers 201 with the updated email. 409 when the email is not a draft; 422 when the total attachment size limit would be exceeded. Requires work-orders.sendEmail on a visible work order.',
    ],
    'POST /work-orders/{workOrder}/emails/{email}/attachments/import' => [
        'summary' => 'Attach existing documents to a work order email draft',
        'description' => 'Copies files onto the draft from source: documents (attachment_ids of documents of the work order or of its client, each needing the matching viewDocuments permission), document_bundle (document_bundle_id of an active bundle) or quote_pdf (the quote PDF generated from its document layout, needs view on the quote). Answers 201 with the updated email. 422 when a document is not available, the bundle is inactive, no layout applies or the size limit is exceeded; 409 when not a draft. Requires work-orders.sendEmail.',
    ],
    'DELETE /work-orders/{workOrder}/emails/{email}/attachments/{attachment}' => [
        'summary' => 'Remove an attachment from a work order email draft',
        'description' => 'Removes one attachment of the draft and returns the updated email. 409 when the email is not a draft; 404 when the attachment does not belong to it. Requires work-orders.sendEmail on a visible work order.',
    ],
    'GET /work-orders/{workOrder}/emails/{email}/attachments/{attachment}/download' => [
        'summary' => 'Download an attachment of a work order email',
        'description' => 'Streams one attachment of the email as a file download, whatever the email status. 404 when the attachment does not belong to the email or the file no longer exists. Requires work-orders.viewEmails on a visible work order.',
    ],
    'POST /work-orders/{workOrder}/emails/{email}/schedule' => [
        'summary' => 'Schedule a work order email as recurring',
        'description' => 'Turns the caller\'s draft into a recurring series: recurrence holds frequency (daily, weekly, monthly, yearly, custom), interval, weekdays for weekly, month_mode fixed (month_day) or ordinal (ordinal plus ordinal_weekday) for monthly and yearly, year_month for yearly, optional workdays_only, starts_on (today or later) and ends_on. A copy is sent at each date; the email becomes scheduled. 403 for a non-author; 409 when not a draft or the work order is completed or closed; 422 when it could not be sent (no recipient, empty subject or body, sender not allowed, attachments too large). Requires work-orders.sendEmail.',
    ],
    'POST /work-orders/{workOrder}/emails/{email}/send' => [
        'summary' => 'Send a work order email',
        'description' => 'Queues the email for sending from its chosen sender address (or the caller\'s login email) and answers 202 with the email in status queued; it becomes sent or failed once processed, so read it again to know the outcome. Only the author can send a draft or resend a failed email (403 otherwise); 409 for any other status; 422 when there is no recipient, subject or body is empty, the sender is not allowed or attachments exceed the size limit. Requires work-orders.sendEmail.',
    ],
    'POST /work-orders/{workOrder}/emails/{email}/unschedule' => [
        'summary' => 'Stop a recurring work order email',
        'description' => 'Stops the recurring series of the caller\'s scheduled (or ended) email and brings it back to a draft, keeping its recurrence rule. 403 for a non-author; 409 when the email is not scheduled. Requires work-orders.sendEmail on a visible work order.',
    ],
    'GET /work-orders/{workOrder}/invoices' => [
        'summary' => 'List the invoices of a work order',
        'description' => 'Returns, read-only, the active proformas and invoices issued on the work order and the purchase invoices with at least one line on it, each with number, dates, counterparty, company, amounts, collected or paid and residual amounts (active ones also with payment status; purchase ones with the amount on this work order), plus totals for both sides. Requires work-orders.viewInvoices on a visible work order.',
    ],
    'POST /work-orders/{workOrder}/proforma-requests' => [
        'summary' => 'Raise the proforma requests of a work order',
        'description' => 'Generates, once per work order, the pending proforma requests for its lines with the required note: one consultancy request per offer, and one institution request per line supplier and issuing company, grouped by the product typology\'s proforma kind (lines whose typology has none are ignored). Each request is assigned to the caller and keeps its offer\'s payment method. Answers 201 with the list of created requests. 409 when requests already exist (pending or issued); 422 when no line is billable. Requires proforma-requests.create and work-orders.view on a visible work order.',
    ],
    'GET /work-orders/{workOrder}/proforma-requests/summary' => [
        'summary' => 'Get the proforma request state of a work order',
        'description' => 'Returns the aggregated proforma request status of the work order (none, pending or issued), the date of the latest request, the offer payment method (null on a multi-offer work order) and the work order id and code. Requires proforma-requests.create and work-orders.view on a visible work order.',
    ],
    'GET /work-orders/{workOrder}/progress-entries' => [
        'summary' => 'List the progress entries of a work order',
        'description' => 'Returns the manual progress entries of the work order with their author. Each entry stores the increment in percentage points, so the current progress is their sum. Requires work-orders.view on a visible work order.',
    ],
    'POST /work-orders/{workOrder}/progress-entries' => [
        'summary' => 'Record the progress of a work order',
        'description' => 'Records the new total progress of the work order: percentage (1-100) is the new total, which must be above the current progress (422 otherwise), and recorded_on its date. The entry stores the increment over the current total. Answers 201 with the entry. Requires work-orders.update on a visible work order.',
    ],
    'DELETE /work-orders/{workOrder}/progress-entries/{progressEntry}' => [
        'summary' => 'Delete a progress entry',
        'description' => 'Deletes one progress entry of the work order, lowering the progress by its increment. Answers with data null; 404 when the entry belongs to another work order. Requires work-orders.update on a visible work order.',
    ],
    'POST /work-orders/{workOrder}/request-update' => [
        'summary' => 'Ask the work order team for an update',
        'description' => 'Sends a notification with message (3-2000 characters) asking for an update on the work order, without changing it. target is participants (supervisors who are not participants get a copy), supervisors, or all. 422 when the work order is completed or closed, or the chosen group is empty. Answers with data null. Requires work-orders.requestUpdate on a visible work order.',
    ],
    'GET /work-orders/{workOrder}/stages' => [
        'summary' => 'List the stages of a work order',
        'description' => 'Returns the stages (phases) of the work order in their order, each with name, sort_order, closed_at and closed_by, and the minutes of time logged on it. Requires work-orders.view on a visible work order.',
    ],
    'POST /work-orders/{workOrder}/stages' => [
        'summary' => 'Create a stage on a work order',
        'description' => 'Adds a stage with the given name at the end of the work order\'s stages. Answers 201 with the stage. 409 when the work order is force-closed. Requires work-orders.update on a visible work order.',
    ],
    'POST /work-orders/{workOrder}/stages/reorder' => [
        'summary' => 'Reorder the stages of a work order',
        'description' => 'Sets the order of the work order\'s stages: stage_ids must list every stage of this work order exactly once, in the new order. Returns the reordered stages. 422 when ids are missing, duplicated or belong to another work order; 409 when the work order is force-closed. Requires work-orders.update on a visible work order.',
    ],
    'PUT /work-orders/{workOrder}/stages/{stage}' => [
        'summary' => 'Rename a work order stage',
        'description' => 'Changes the name of a stage of the work order and returns it. 404 when the stage belongs to another work order; 409 when the work order is force-closed. Requires work-orders.update on a visible work order.',
    ],
    'DELETE /work-orders/{workOrder}/stages/{stage}' => [
        'summary' => 'Delete a work order stage',
        'description' => 'Deletes a stage; its tasks are not deleted but moved to the end of the "no stage" group, keeping their relative order. Answers 200 with data null. 409 when the work order is force-closed. Requires work-orders.update on a visible work order.',
    ],
    'POST /work-orders/{workOrder}/stages/{stage}/close' => [
        'summary' => 'Close a work order stage',
        'description' => 'Marks the stage closed by the caller, so no task can be moved into it. 409 when any of its tasks or sub-tasks is not closed, with data.open_tasks_count, or when the work order is force-closed. Returns the stage. Requires work-orders.update on a visible work order.',
    ],
    'POST /work-orders/{workOrder}/stages/{stage}/reopen' => [
        'summary' => 'Reopen a work order stage',
        'description' => 'Clears the closure of a stage and returns it. 409 when the work order is force-closed. Requires work-orders.update on a visible work order.',
    ],
    'GET /work-orders/{workOrder}/task-board' => [
        'summary' => 'Get the task board of a work order',
        'description' => 'Returns everything a list or kanban view of the work order\'s tasks needs: its stages (with logged minutes), its tasks each with the caller\'s permissions on it, is_read_only (true when the work order is force-closed or the caller cannot update it) and the minutes logged on tasks without a stage. Requires work-orders.view on a visible work order and tasks.viewAny.',
    ],
    'POST /work-orders/{workOrder}/task-board/bulk' => [
        'summary' => 'Apply a bulk action to work order tasks',
        'description' => 'Applies action to up to 200 root tasks of the work order (task_ids): assign (assignee_ids), complete (time_entry with the total minutes split among the tasks, optional closure_feedback and for_all_assignees), uncomplete, block, priority (an active task_priority_id) or dates (start_date, end_date). Each task is checked and saved on its own: results lists ok and message per task with succeeded and failed counts. complete is all or nothing: one incompatible task gives 422 with incompatible_tasks and nothing is closed. 422 when a task is not a root of this work order; 409 when it is force-closed. Requires work-orders.view.',
    ],
    'POST /work-orders/{workOrder}/task-board/move' => [
        'summary' => 'Move a task on the work order board',
        'description' => 'Moves the root task task_id into work_order_stage_id (null for the "no stage" group) at the zero-based position, counted after removing it from its origin group. Returns tasks, the new stage and position of the affected rows. 422 when the task is a sub-task or belongs to another work order, or the stage belongs to another work order; 409 when the stage is closed or the work order is force-closed. Requires update permission on the task.',
    ],
];
