import type { Billing } from '@/features/management-control/billing-types'

/** Backlog by business function, age and proforma cycle; every sum of the contract invariants holds. */
export function makeBilling(overrides: Partial<Billing> = {}): Billing {
  return {
    applied: {
      date_from: '2026-01-01',
      date_to: '2026-10-16',
      company_id: [],
      company_site_id: [],
      operational_site_id: [],
      group_by: 'business_function',
    },
    today: '2026-10-16',
    backlog: {
      work_orders_count: 5,
      contract: 10000,
      invoiced: 7500,
      to_invoice: 2500,
      by_dimension: [
        { key: '1', label: 'Engineering', work_orders_count: 3, contract: 6000, invoiced: 4000, to_invoice: 2000 },
        { key: '2', label: 'Training', work_orders_count: 2, contract: 3000, invoiced: 3500, to_invoice: -500 },
        { key: 'unattributed', label: null, work_orders_count: 1, contract: 1000, invoiced: 0, to_invoice: 1000 },
      ],
      by_age: [
        { bucket: '0_3', work_orders_count: 2, to_invoice: 1500 },
        { bucket: '3_6', work_orders_count: 1, to_invoice: 1500 },
        { bucket: '6_12', work_orders_count: 1, to_invoice: 0 },
        { bucket: 'over_12', work_orders_count: 1, to_invoice: -500 },
      ],
    },
    proforma: {
      issued: { documents: 6, amount: 12000 },
      converted: { documents: 3, amount: 5000, average_days_to_invoice: 20 },
      open: { documents: 3, amount: 7000 },
      open_by_age: [
        { bucket: '0_30', documents: 1, amount: 1000 },
        { bucket: '31_60', documents: 1, amount: 2000 },
        { bucket: '61_90', documents: 0, amount: 0 },
        { bucket: 'over_90', documents: 1, amount: 4000 },
      ],
      average_days_invoice_to_collection: 13,
      by_kind: [
        { kind: 'consultancy', issued: 3, converted: 2, open: 1, average_days_to_invoice: 15 },
        { kind: 'institution', issued: 2, converted: 1, open: 1, average_days_to_invoice: null },
        { kind: null, issued: 1, converted: 0, open: 1, average_days_to_invoice: null },
      ],
    },
    ...overrides,
  }
}
