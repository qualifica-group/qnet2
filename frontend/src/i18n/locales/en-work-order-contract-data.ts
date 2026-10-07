/**
 * Work order contract data (spec 0201): the "Contract data" tab of the work
 * order detail, nested in `workOrders.contractData` (sibling file:
 * `en-work-orders.ts` is close to the size limit, see
 * `.claude/rules/engineering.md` §6).
 */

export const workOrderContractData = {
  title: 'Contract data',
  loading: 'Loading contract data',
  loadError: 'Unable to load the work order contract data.',
  empty: 'The work order has no product lines.',
  tableCaption: 'Contract data per product line: amounts, effective revenue and payment',
  columns: {
    product: 'Product',
    quantity: 'Qty',
    unitPrice: 'Unit price',
    netAmount: 'Net amount',
    supplierCommission: 'Supplier commission',
    netOfCommissions: 'Net of commissions',
    effectiveRevenue: 'Effective revenue',
    payment: 'Payment',
  },
  kind: {
    consultancy: 'Consulting',
  },
  totals: {
    netAmount: 'Net amount',
    typologyNet: 'Net amount',
    typologyRevenue: 'Revenue',
    revenue: 'Total revenue',
    commissions: 'Commissions',
    netOfCommissions: 'Net of commissions',
  },
  formula: {
    net: 'Net amount {{quantity}} × {{unitPrice}} = {{net}} → revenue {{revenue}}',
    paid: '{{net}} · Supplier commission paid: {{commission}} (a cost, it does not reduce the revenue)',
    paidPercentage: '{{rate}}% of {{base}} = {{amount}}',
    paidAmount: '{{amount}}',
    received: 'Supplier commission received: {{commission}} → revenue {{revenue}}',
    receivedPercentage: '{{rate}}% of {{base}} = {{amount}}',
    receivedAmount: 'fixed {{amount}}',
    receivedMissing: 'Supplier commission received missing → revenue {{revenue}}',
    receivedHidden: 'Supplier commission received: revenue {{revenue}}',
    netOfCommissions: 'Net of commissions: {{net}} − {{commissions}} = {{result}}',
  },
  warnings: {
    missingSupplierCommission: 'Supplier commission received missing: the revenue is 0.00.',
    staleCommissionBase: 'Amount calculated on a previous base: save the quote again to update it.',
  },
  payment: {
    noStatus: 'No status',
    unpaid: 'Unpaid',
    edit: 'Edit the payment of {{product}}',
  },
  editor: {
    status: 'Payment status',
    noStatus: 'No status',
    agreement: 'Payment agreement',
    agreementMax: 'The agreement can be at most 2000 characters.',
    unpaid: 'Unpaid',
    done: 'Done',
    reset: 'Reset',
    saveError: 'Unable to save the payment data. Please try again.',
  },
}
