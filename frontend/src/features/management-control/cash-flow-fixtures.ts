import type { CashFlow } from '@/features/management-control/cash-flow-types'

/** Three months from October 2026; every figure adds up (net = receivable - payable - committed, by_company sums to the totals). */
export function makeCashFlow(overrides: Partial<CashFlow> = {}): CashFlow {
  return {
    applied: { company_id: [], company_site_id: [], operational_site_id: [], document_type: 'all', horizon_months: 3 },
    today: '2026-10-16',
    backlog: { receivable: 500, payable: 200, committed: 100, net: 200 },
    months: [
      { month: '2026-10', receivable: 1000, payable: 400, committed: 100, net: 500, cumulative: 500 },
      { month: '2026-11', receivable: 0, payable: 300, committed: 0, net: -300, cumulative: 200 },
      { month: '2026-12', receivable: 600, payable: 100, committed: 0, net: 500, cumulative: 700 },
    ],
    beyond: { receivable: 200, payable: 0, committed: 50, net: 150 },
    by_company: [
      {
        key: '1',
        label: 'Acme Spa',
        backlog_net: 150,
        months: [
          { month: '2026-10', net: 400 },
          { month: '2026-11', net: -100 },
          { month: '2026-12', net: 300 },
        ],
        beyond_net: 100,
        total_net: 850,
      },
      {
        key: 'unattributed',
        label: null,
        backlog_net: 50,
        months: [
          { month: '2026-10', net: 100 },
          { month: '2026-11', net: -200 },
          { month: '2026-12', net: 200 },
        ],
        beyond_net: 50,
        total_net: 200,
      },
    ],
    ...overrides,
  }
}
