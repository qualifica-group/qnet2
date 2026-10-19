/** Work order "Active proforma" / "Passive proforma" tabs (spec 0282), nested at `workOrders.invoices`. */

export const workOrderInvoices = {
  tabs: {
    active: 'Active proforma',
    passive: 'Passive proforma',
  },
  loading: 'Loading documents',
  loadError: 'Unable to load the work order proforma and invoices.',
  totals: 'Totals',
  columns: {
    type: 'Type',
    number: 'Number',
    date: 'Date',
    externalNumber: 'External number',
    customer: 'Customer',
    supplier: 'Supplier',
    total: 'Total',
    collected: 'Collected',
    paymentStatus: 'Collection status',
    documentTotal: 'Document total',
    workOrderAmount: 'Work order share',
    paid: 'Paid',
    residual: 'Residual',
  },
  active: {
    caption: 'Customer proforma and invoices of the work order',
    empty: 'No active proforma or invoice for this work order.',
  },
  passive: {
    caption: 'Supplier proforma and invoices with lines on the work order',
    empty: 'No passive proforma or invoice for this work order.',
  },
}
