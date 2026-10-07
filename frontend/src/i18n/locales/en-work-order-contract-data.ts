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
    actions: 'Actions',
  },
  /** Abbreviated headers of the wide table; the full label stays as accessible name and tooltip. */
  columnsShort: {
    unitPrice: 'Price',
    supplierCommission: 'Suppl. comm.',
    netOfCommissions: 'Net of comm.',
    effectiveRevenue: 'Revenue',
  },
  kind: {
    consultancy: 'Consulting',
  },
  totals: {
    netAmount: 'Net amount',
    revenue: 'Total revenue',
    commissions: 'Commissions',
    netOfCommissions: 'Net of commissions',
    byTypology: 'Revenue by typology (net amount → revenue)',
  },
  hint: {
    netAmountRule: 'Quantity × unit price.',
    netAmountFormula: '{{quantity}} × {{unitPrice}} = {{net}}',
    commissionTitle: {
      RECEIVED: 'Supplier commission received',
      PAID: 'Supplier commission paid',
    },
    commissionPercentage: '{{rate}}% of {{base}} = {{amount}}',
    commissionFixed: 'Fixed amount: {{amount}}',
    commissionBase: 'The base is the line margin.',
    netOfCommissionsRule: 'Net amount minus all the line commissions.',
    netOfCommissionsFormula: '{{net}} − {{commissions}} = {{result}}',
    revenueReceived: 'Supplier commission received: the revenue is the commission, not the net amount.',
    revenuePaid: 'Supplier commission paid: the revenue is the net amount, the commission is a cost.',
    revenueNone: 'Supplier commission not calculated: the revenue is the net amount.',
    revenueFrom: '{{detail}} → revenue {{revenue}}',
    revenueOnly: 'Revenue {{revenue}}',
    receivedMissing: 'Commission missing → revenue {{revenue}}',
    totals: {
      netAmount: 'Sum of the net amounts of all the lines.',
      revenue: 'Sum of the lines\' effective revenue: the received commission, otherwise the net amount.',
      commissions: 'Sum of the commissions of all the lines.',
      netOfCommissions: 'Net amount minus commissions.',
    },
  },
  warnings: {
    title: 'Warning',
    missingSupplierCommission: 'Supplier commission received missing: the revenue is 0.00.',
    staleCommissionBase: 'Amount calculated on a previous base: save the quote again to update it.',
  },
  payment: {
    noStatus: 'No status',
    unpaid: 'Unpaid',
    edit: 'Edit the payment of {{product}}',
  },
  editor: {
    title: 'Line payment: {{product}}',
    description: 'Payment status, agreement and unpaid flag of the line.',
    status: 'Payment status',
    noStatus: 'No status',
    agreement: 'Payment agreement',
    agreementMax: 'The agreement can be at most 2000 characters.',
    unpaid: 'Unpaid',
    save: 'Save',
    saveError: 'Unable to save the payment data. Please try again.',
  },
}
