/** Management control, accounts (spec 0271). */
export const accounts = {
  loading: 'Loading accounts',
  error: 'Unable to load accounts.',
  empty: 'No collection and no payment in the chosen period with these filters.',
  filters: {
    title: 'Accounts filters',
    dateFrom: 'Collections and payments from',
  },
  totals: {
    title: 'Totals of the period',
    collections: 'Collections',
    payments: 'Payments',
    net: 'Net',
  },
  types: { bank_account: 'Bank account', card: 'Card', cash: 'Cash' },
  collections: {
    title: 'Collections by account',
    hint: 'Receivable installments collected in the period, on the document total.',
    caption: 'Collections of the period by financial account',
  },
  payments: {
    title: 'Payments by account',
    hint: 'Payable installments paid in the period, on the document total. With no account on the installment, the invoice account applies.',
    caption: 'Payments of the period by financial account',
  },
  table: {
    account: 'Account',
    type: 'Type',
    noType: '-',
    amount: 'Amount',
    installments: 'Installments',
    total: 'Total',
  },
  chart: {
    title: 'Trend',
    monthsTitle: 'Collections and payments by month',
    collectionsTitle: 'Collections split by account',
    paymentsTitle: 'Payments split by account',
    empty: 'No data in the period.',
  },
}
