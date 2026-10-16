import type { Agents } from '@/features/management-control/agents-types'

/**
 * Three months to October 2026, top 2 of 4 agents: the listed agents plus the others add up to the totals of
 * every figure and of the pending documents, and the months add up to accrued and paid.
 */
export function makeAgents(overrides: Partial<Agents> = {}): Agents {
  return {
    applied: { date_from: '2026-08-01', date_to: '2026-10-16', company_id: [], top: 2 },
    today: '2026-10-16',
    totals: {
      accrued: 9000,
      to_settle: 4000,
      in_statement: 3000,
      approved_unpaid: 1500,
      paid: 2500,
      agents_count: 4,
      pending_document_count: 3,
    },
    agents: [
      {
        referent: { id: 11, name: 'Mario Rossi' },
        pending_document_count: 1,
        accrued: 4000,
        to_settle: 2500,
        in_statement: 1800,
        approved_unpaid: 500,
        paid: 1000,
      },
      {
        referent: { id: 12, name: 'Anna Bianchi' },
        pending_document_count: 1,
        accrued: 2000,
        to_settle: 1000,
        in_statement: 1200,
        approved_unpaid: 700,
        paid: 500,
      },
    ],
    others: {
      agents_count: 2,
      pending_document_count: 1,
      accrued: 3000,
      to_settle: 500,
      in_statement: 0,
      approved_unpaid: 300,
      paid: 1000,
    },
    by_month: [
      { month: '2026-08', accrued: 3000, paid: 500 },
      { month: '2026-09', accrued: 2000, paid: 1000 },
      { month: '2026-10', accrued: 4000, paid: 1000 },
    ],
    ...overrides,
  }
}
