/**
 * Purchase orders (ODA) domain (spec 0210). Side file to keep `en.ts` within
 * the size limits (see `.claude/rules/engineering.md` §6). Covers the list,
 * the status vocabulary and the line labels; the create/edit form keeps its
 * own strings in a separate side file.
 */

export const purchaseOrders = {
  title: 'Purchase orders',
  subtitle: 'Browse and manage the purchase orders (ODA).',
  forbidden: 'You do not have permission to view purchase orders.',
  newOrder: 'New ODA',
  formInDevelopment: 'Purchase order form: module under development.',
  columns: {
    id: 'ID',
    code: 'Number',
    number: 'No.',
    year: 'Year',
    order_date: 'Order date',
    status: 'Status',
    supplier: 'Supplier',
    supplier_vat_number: 'Supplier VAT no.',
    company: 'Company',
    company_site: 'Company site',
    operational_site: 'Operational site',
    description: 'Description',
    payment_method: 'Payment method',
    customer: 'Customer',
    work_order: 'Work order',
    quote: 'Contract/Offer',
    opportunity: 'Opportunity',
    sectors: 'Sectors',
    is_internal_user: 'Internal user',
    taxable_total: 'Taxable',
    vat_total: 'VAT',
    grand_total: 'Total',
    lines_count: 'Lines',
    created_by: 'Created by',
    created_at: 'Created at',
  },
  statuses: {
    draft: 'Draft',
    in_validation: 'In validation',
    approved: 'Approved',
    sent_to_supplier: 'Sent to supplier',
    partially_fulfilled: 'Partially fulfilled',
    fulfilled: 'Fulfilled',
    cancelled: 'Cancelled',
  },
  tabs: {
    label: 'Filter by status',
    all: 'All',
  },
  actions: {
    activity: 'Activity',
    delete: 'Delete',
    pdf: 'Download PDF',
  },
  sections: {
    lines: 'Lines',
  },
  lines: {
    description: 'Description',
    costType: 'Cost type',
    unit: 'Unit',
    quantity: 'Quantity',
    unitPrice: 'Unit price',
    taxable: 'Taxable',
    vat: 'VAT',
    total: 'Total',
  },
  form: {
    loadError: 'Unable to load the purchase order. Please try again.',
  },
  messages: {
    deleted: 'Purchase order deleted.',
    deleteForbidden: 'You cannot delete this purchase order.',
    deleteBlocked: 'Only draft or in-validation purchase orders can be deleted.',
    deleteError: 'Unable to delete the purchase order. Please try again.',
  },
}
