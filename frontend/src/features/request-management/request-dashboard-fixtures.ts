import type { RequestDashboardData } from '@/features/request-management/dashboard-api'
import type { RequestReportCategory } from '@/features/request-management/report-api'

/**
 * The statistics dashboard's shared test fixture (spec 0107/0192): pure data,
 * no `vi.mock` (those stay in the suite that hoists them), so every dashboard
 * suite builds the same two-category response.
 */

export const DASHBOARD_CATEGORIES: RequestReportCategory[] = [
  { key: 'gol', label: 'GOL', depth: 0, parent_key: null },
  { key: 'consulenza', label: 'Consulenza', depth: 0, parent_key: null },
]

export function dashboardData(overrides: Partial<RequestDashboardData> = {}): RequestDashboardData {
  return {
    applied: {
      date_from: '2026-09-07',
      date_to: '2026-09-11',
      category_keys: ['gol', 'consulenza'],
      row_mode: 'all',
      operator_keys: null,
      site_keys: null,
    },
    summary: [{ key: 'phone_calls', label: 'N. Telefonate Effettuate', value: 12 }],
    categories: [
      {
        key: 'gol',
        label: 'GOL',
        summary: [
          { key: 'phone_calls', label: 'N. Telefonate Effettuate', value: 8 },
          { key: 'aule_gestione', label: 'Aule in gestione', value: 0 },
        ],
        charts: [
          {
            id: 'indicator-gol',
            scope: 'indicator',
            indicator_key: null,
            indicator_label: null,
            points: [
              { label: 'N. Telefonate Effettuate', value: 8 },
              { label: 'Aule in gestione', value: 0 },
            ],
          },
        ],
      },
      {
        key: 'consulenza',
        label: 'Consulenza',
        summary: [{ key: 'phone_calls', label: 'N. Telefonate Effettuate', value: 4 }],
        charts: [
          {
            id: 'operator-consulenza-phone_calls',
            scope: 'operator',
            indicator_key: 'phone_calls',
            indicator_label: 'N. Telefonate Effettuate',
            points: [{ label: 'Ada Rossi', value: 4 }],
          },
        ],
      },
    ],
    ...overrides,
  }
}
