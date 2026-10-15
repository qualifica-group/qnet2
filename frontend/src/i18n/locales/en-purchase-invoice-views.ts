/**
 * Passive invoices (spec 0250): list, detail sheet, deletion and the
 * "Register invoice" action from the ODA. The editor and supplier-details
 * dialog strings live in `en-purchase-invoices.ts`. Side file to stay within
 * the size limits (`engineering.md` §6).
 */

export const purchaseInvoiceViews = {
  title: 'Passive invoices',
  subtitle: 'Supplier invoices and proformas with lines, installments, linked ODAs and documents.',
  forbidden: 'You do not have permission to view passive invoices.',
  newInvoice: 'New passive invoice',
  columns: {
    document_type: 'Type',
    document_number: 'Supplier number',
    document_date: 'Document date',
    supplier: 'Supplier',
    company: 'Company',
    purchase_orders: 'Linked POs',
    net_amount: 'Net amount',
    vat_amount: 'VAT',
    total_amount: 'Total',
    paid_amount: 'Paid',
    residual_amount: 'Residual',
    payment_status: 'Payment status',
  },
  actions: {
    view: 'View',
    delete: 'Delete',
    activity: 'Activity',
  },
  detail: {
    title: 'Invoice {{number}}',
    titleProforma: 'Passive proforma',
    loadError: 'Unable to load the document. Try again.',
    delete: 'Delete',
    header: 'Header',
    totals: 'Totals',
    notes: 'Notes',
    fields: {
      document_date: 'Document date',
      company: 'Company',
      supplier: 'Supplier',
      payment_method: 'Payment method',
      financial_account: 'Account',
      company_site: 'Company site',
      operational_site: 'Operational site',
      cost_type: 'Default cost type',
      work_order: 'Default work order',
      created_by: 'Registered by',
    },
    lines: {
      title: 'Lines',
      empty: 'No lines.',
      columns: {
        description: 'Description',
        order: 'ODA',
        quantity: 'Quantity',
        unit_price: 'Price',
        vat_rate: 'VAT',
        cost_type: 'Cost type',
        work_order: 'Work order',
        net_amount: 'Net amount',
        vat_amount: 'VAT',
        total_amount: 'Total',
        actions: 'Actions',
      },
    },
    installments: {
      title: 'Installments',
      empty: 'No installments.',
      columns: {
        sequence: 'No.',
        due_date: 'Due date',
        amount: 'Amount',
        payment_method_code: 'Method code',
        status: 'Status',
        paid_amount: 'Paid',
        paid_at: 'Payment date',
      },
    },
    orders: {
      title: 'Linked ODAs',
      empty: 'No linked ODA.',
    },
    documents: {
      title: 'Documents',
    },
  },
  delete: {
    title: 'Delete the document?',
    description: 'Document {{number}} will be deleted with its lines, installments and attachments. This cannot be undone.',
    confirm: 'Delete',
    cancel: 'Cancel',
    deleted: 'Document deleted.',
    locked: 'Unable to delete: a registered invoice or a document with payments cannot be deleted.',
    genericError: 'Unable to delete the document. Try again.',
  },
  registerInvoice: {
    button: 'Register invoice',
    tooltip: 'Register the passive invoice of this order',
  },
  record: {
    registration: 'Registration and notes',
    saved: 'Document updated.',
    saveError: 'Unable to save the document. Try again.',
    invalid: 'Check the highlighted fields.',
  },
}
