/**
 * Proforma requests domain (spec 0193). Side file to keep `en.ts` within the
 * size limits (see `.claude/rules/engineering.md` §6). Covers both the
 * Accounting > Receivables module and the "€" button and modal of the work
 * orders list.
 */

export const proformaRequests = {
  title: 'Proforma requests',
  subtitle: 'Browse the proforma issue requests sent to Accounting.',
  forbidden: 'You do not have permission to view proforma requests.',
  columns: {
    id: 'ID',
    work_order_code: 'Work order no.',
    work_order_title: 'Work order title',
    company: 'Company',
    customer: 'Customer',
    kind: 'Type',
    supplier: 'Supplier',
    payment_method: 'Payment method',
    status: 'Status',
    note: 'Notes',
    assigned_to: 'Assigned to',
    assigned_by: 'Requested by',
    created_at: 'Requested on',
  },
  kinds: {
    consultancy: 'Consultancy',
    institution: 'Institution',
  },
  statuses: {
    pending: 'Pending',
    issued: 'Issued',
  },
  detail: {
    title: 'Proforma request: Work order #{{code}}',
    sectionTitle: 'Request details',
    issuedAt: 'Issued on',
    loadError: 'Unable to load the request. Please try again.',
  },
  form: {
    editTitle: 'Edit request',
    editSubtitle: 'Only the note for Accounting can be edited.',
    workOrderLine: 'Work order #{{code}}',
    note: 'Notes for Accounting',
    noteRequired: 'The note for Accounting is required.',
    noteTooLong: 'The note cannot exceed {{max}} characters.',
    save: 'Save',
    updated: 'Request updated.',
    genericError: 'Unable to save the request. Please try again.',
    deleted: 'Request deleted.',
    deleteForbidden: 'You do not have permission to delete this request.',
    deleteError: 'Unable to delete the request. Please try again.',
  },
  cell: {
    none: 'Request proforma issue',
    pending: 'Proforma requested: open the request',
    issued: 'Proforma issued',
  },
  dialog: {
    title: 'Proforma issue request: Work order #{{code}}',
    description: 'Send Accounting the request to issue the proforma for this work order.',
    paymentMethod: 'Payment method',
    paymentMethodNone: 'Not specified',
    lastRequest: 'Last request on {{date}}',
    close: 'Close',
    send: 'Send request',
    sent: 'Proforma request sent.',
    sendError: 'Unable to send the request. Please try again.',
    loadError: 'Unable to load the work order data. Please try again.',
  },
}
