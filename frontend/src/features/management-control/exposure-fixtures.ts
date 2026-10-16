import type { Exposure, ExposureSide, StatusCell } from '@/features/management-control/exposure-types'

const NO_STATUS: StatusCell = { documents: 0, open: 0 }

export function makeExposureSide(overrides: Partial<ExposureSide> = {}): ExposureSide {
  return {
    open_total: 1000,
    overdue_total: 600,
    not_due_total: 400,
    aging: {
      overdue_over_90: 100,
      overdue_61_90: 150,
      overdue_31_60: 150,
      overdue_1_30: 200,
      due_0_30: 250,
      due_31_60: 100,
      due_over_60: 50,
    },
    invoiced_in_period: 5000,
    settled_in_period: 3000,
    average_days: 42,
    by_status: {
      paid: { documents: 7, open: 0 },
      not_due: { documents: 3, open: 400 },
      overdue: { documents: 2, open: 250 },
      seriously_overdue: { documents: 1, open: 350 },
    },
    top: [
      { registry: { id: 11, name: 'Alfa Srl' }, open: 700, overdue: 500, worst_bucket: 'overdue_61_90' },
      { registry: { id: 12, name: 'Beta Spa' }, open: 300, overdue: 100, worst_bucket: 'overdue_1_30' },
    ],
    ...overrides,
  }
}

export function makeExposure(overrides: Partial<Exposure> = {}): Exposure {
  return {
    applied: {
      date_from: '2026-01-01',
      date_to: '2026-06-30',
      company_id: [],
      company_site_id: [],
      operational_site_id: [],
      document_type: 'invoice',
      group_by: 'company',
      top: 10,
    },
    today: '2026-10-16',
    receivable: makeExposureSide(),
    payable: {
      ...makeExposureSide({
        open_total: 800,
        overdue_total: 300,
        not_due_total: 500,
        average_days: null,
        by_status: { paid: NO_STATUS, not_due: { documents: 4, open: 500 }, overdue: NO_STATUS, seriously_overdue: { documents: 2, open: 300 } },
        top: [{ registry: { id: 21, name: 'Fornitore Uno' }, open: 800, overdue: 300, worst_bucket: 'overdue_31_60' }],
      }),
      by_cost_type: [
        { cost_type: { id: 1, name: 'Rent', color: '#336699', nature: 'general' }, open: 500, overdue: 100 },
        { cost_type: null, open: 300, overdue: 200 },
      ],
    },
    committed: {
      total: 250,
      with_work_order: 100,
      without_work_order: 150,
      by_supplier: [{ registry: { id: 22, name: 'Fornitore Due' }, committed: 250, orders: 3 }],
      by_cost_type: [{ cost_type: { id: 2, name: 'Consulting', color: '#aa5500', nature: 'direct' }, open: 250, overdue: 0 }],
    },
    exposure: {
      receivable_open: 1000,
      payable_open: 800,
      committed_total: 250,
      net: -50,
      by_dimension: [
        { key: '1', label: 'Acme Spa', receivable_open: 1000, payable_open: 600, committed: 100, net: 300 },
        { key: 'unattributed', label: null, receivable_open: 0, payable_open: 200, committed: 150, net: -350 },
      ],
    },
    concentration: {
      invoiced_total: 1000,
      overdue_total: 200,
      top_invoiced_share: 70,
      top_overdue_share: 25,
      rows: [
        { registry: { id: 11, name: 'Alfa Srl' }, invoiced: 400, invoiced_share: 40, overdue: 50, overdue_share: 25 },
        { registry: { id: 12, name: 'Beta Spa' }, invoiced: 300, invoiced_share: 30, overdue: 0, overdue_share: 0 },
      ],
    },
    ...overrides,
  }
}
