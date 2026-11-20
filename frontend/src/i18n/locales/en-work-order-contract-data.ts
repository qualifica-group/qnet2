/**
 * Work order contract data (spec 0201): the "Contract data" tab of the work
 * order detail, nested in `workOrders.contractData` (sibling file:
 * `en-work-orders.ts` is close to the size limit, see
 * `.claude/rules/engineering.md` §6).
 */

export const workOrderContractData = {
  title: 'Work order contract data',
  loading: 'Loading contract data',
  loadError: 'Unable to load the work order contract data.',
  empty: 'The work order has no product lines.',
  fields: {
    title: 'Title',
    amount: 'Amount',
    paymentStatus: 'Payment status',
    document: 'Document',
    noDocument: 'No proforma issued',
    agreement: 'Payment agreement',
    unpaid: 'Unpaid',
  },
  kind: {
    consultancy: 'Consulting',
  },
  totals: {
    revenue: 'Total revenue',
    commissions: 'Commissions',
    netOfCommissions: 'Net of commissions',
    typology: 'Total {{name}}',
    typologyRevenue: 'revenue {{revenue}}',
  },
  hint: {
    netAmountRule: 'Quantity × unit price.',
    netAmountFormula: '{{quantity}} × {{unitPrice}} = {{net}}',
    netOfCommissionsFormula: '{{net}} − {{commissions}} = {{result}}',
    totals: {
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
  bulk: {
    setAll: 'Set payment on every line',
    title_one: 'Payment of {{count}} line',
    title_other: 'Payment of {{count}} lines',
    description: 'The chosen values apply to every line of the work order; "Keep unchanged" leaves each line as it is.',
    keep: 'Keep unchanged',
    unpaidYes: 'Yes',
    unpaidNo: 'No',
    nothingToSet: 'Choose at least one value to set.',
    apply: 'Apply',
    saved_one: 'Payment updated on {{count}} line.',
    saved_other: 'Payment updated on {{count}} lines.',
  },
}
