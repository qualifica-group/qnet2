/** Management control, agents (spec 0272). */
export const agents = {
  title: 'Agents',
  loading: 'Loading the agents',
  error: 'Unable to load the agents.',
  empty: 'No commission accrued, in a statement or paid in the selected period with these filters.',
  filters: {
    title: 'Agents filters',
    dateFrom: 'Accruals and payments from',
    top: 'Agents shown',
    topOption: 'Top {{count}}',
  },
  figures: {
    accrued: 'Accrued',
    to_settle: 'To settle',
    in_statement: 'In statement',
    approved_unpaid: 'Approved, unpaid',
    paid: 'Paid',
  },
  totals: {
    title: 'Totals of the period',
    pendingDocument: '{{count}} awaiting document',
    agents_count: 'Agents',
  },
  table: {
    title: 'Commissions by agent',
    hint: 'Accrued and paid refer to the period; to settle, in statement and approved unpaid are as of today. Agents are sorted by amount to settle.',
    caption: 'Commissions by agent: accrued, to settle, in statement, approved and paid',
    agent: 'Agent',
    pendingDocument: 'Awaiting document',
    others: 'Others ({{count}} agents)',
    total: 'Total',
  },
  chart: {
    title: 'Accrued and paid',
    agentsTitle: 'Accrued and paid by agent',
    monthsTitle: 'Accrued and paid by month',
    empty: 'No data in the period.',
  },
}
