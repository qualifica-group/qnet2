import type { MonthlyTrend } from '@/features/management-control/trend-types'

/** Three months to October 2026; the functions add up to the totals, cost includes labor and margin = revenue - cost everywhere. */
export function makeMonthlyTrend(overrides: Partial<MonthlyTrend> = {}): MonthlyTrend {
  return {
    applied: { date_from: '2026-08-01', date_to: '2026-10-31', company_id: [], company_site_id: [], operational_site_id: [] },
    months: ['2026-08', '2026-09', '2026-10'],
    functions: [
      { key: '1', label: 'Engineering', revenue: [1000, 2000, 3000], cost: [400, 500, 600], labor: [100, 150, 200], margin: [600, 1500, 2400] },
      { key: 'structure', label: null, revenue: [0, 0, 0], cost: [100, 100, 100], labor: [20, 20, 20], margin: [-100, -100, -100] },
      { key: 'unattributed', label: null, revenue: [200, 0, 0], cost: [0, 0, 0], labor: [0, 0, 0], margin: [200, 0, 0] },
    ],
    totals: {
      revenue: [1200, 2000, 3000],
      cost: [500, 600, 700],
      labor: [120, 170, 220],
      labor_minutes: [360, 510, 660],
      unpriced_minutes: [0, 60, 0],
      margin: [700, 1400, 2300],
      previous_year: {
        revenue: [600, 0, 1500],
        cost: [0, 0, 0],
        labor: [0, 0, 0],
        margin: [600, 0, 1500],
      },
    },
    by_nature: [
      { nature: 'direct', cost: [300, 400, 500], share_of_revenue: [25, 20, 16.7] },
      { nature: 'indirect', cost: [100, 100, 100], share_of_revenue: [8.3, 5, 3.3] },
      { nature: 'general', cost: [100, 100, 100], share_of_revenue: [8.3, 5, 3.3] },
      { nature: null, cost: [0, 0, 0], share_of_revenue: [0, 0, 0] },
    ],
    ...overrides,
  }
}
