import type { AccountFlows } from '@/features/management-control/accounts-types'

/** Three months to October 2026; the accounts and the months both add up to the totals of each side. */
export function makeAccountFlows(overrides: Partial<AccountFlows> = {}): AccountFlows {
  return {
    applied: {
      date_from: '2026-08-01',
      date_to: '2026-10-16',
      company_id: [],
      company_site_id: [],
      operational_site_id: [],
    },
    collections: {
      total: 5000,
      installments: 4,
      by_account: [
        { account: { id: 1, name: 'Intesa Main', type: 'bank_account' }, amount: 3500, installments: 2 },
        { account: { id: 2, name: 'Petty cash', type: 'cash' }, amount: 1000, installments: 1 },
        { account: null, amount: 500, installments: 1 },
      ],
    },
    payments: {
      total: 3200,
      installments: 3,
      by_account: [
        { account: { id: 1, name: 'Intesa Main', type: 'bank_account' }, amount: 2000, installments: 1 },
        { account: { id: 3, name: 'Company card', type: 'card' }, amount: 1200, installments: 2 },
      ],
    },
    by_month: [
      { month: '2026-08', collected: 1000, paid: 700, net: 300 },
      { month: '2026-09', collected: 0, paid: 1500, net: -1500 },
      { month: '2026-10', collected: 4000, paid: 1000, net: 3000 },
    ],
    ...overrides,
  }
}
