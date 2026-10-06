import type { ReactNode } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { RequestDashboard } from '@/features/request-management/request-dashboard'
import { DASHBOARD_CATEGORIES, dashboardData } from '@/features/request-management/request-dashboard-fixtures'
import { ENROLLEE_MODULE } from '@/features/request-management/request-module'
import { REQUEST_MANAGEMENT_DOMAIN } from '@/features/request-management/types'

/**
 * Spec 0192: the redesigned statistics page — overview and category tabs,
 * heatmap, operator ranking, previous-period comparison and one-click
 * periods — driven through the same mocked contract as
 * `request-dashboard.test.tsx` (filters, states and errors live there).
 * Every assertion queries by accessible role/label, never `data-testid`.
 */

const fetchRequestManagementReportCategoriesMock = vi.fn()
const fetchRequestManagementReportOperatorsMock = vi.fn()
const fetchRequestManagementReportSitesMock = vi.fn()
vi.mock('@/features/request-management/report-api', () => ({
  fetchRequestManagementReportCategories: (...args: unknown[]) =>
    fetchRequestManagementReportCategoriesMock(...args),
  fetchRequestManagementReportOperators: (...args: unknown[]) =>
    fetchRequestManagementReportOperatorsMock(...args),
  fetchRequestManagementReportSites: (...args: unknown[]) => fetchRequestManagementReportSitesMock(...args),
  // Pulled in by the filter sheet's `useRequestReport`; no test here drives a run.
  createRequestManagementReport: vi.fn(),
  getRequestManagementReport: vi.fn(),
  downloadRequestManagementReport: vi.fn(),
}))

// The filter bar renders the CSV action behind `<Can>`; without this the real
// hook would reach for the auth context this test does not mount.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, isLoading: false }),
}))

const fetchRequestManagementDashboardMock = vi.fn()
vi.mock('@/features/request-management/dashboard-api', () => ({
  fetchRequestManagementDashboard: (...args: unknown[]) => fetchRequestManagementDashboardMock(...args),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  // The applied filters are persisted (user directive 2026-09-08): without this
  // one test's selection would seed the next one's mount.
  window.localStorage.clear()
  fetchRequestManagementReportCategoriesMock.mockReset().mockResolvedValue(DASHBOARD_CATEGORIES)
  // The GA2 list is opted into by the ranking test, empty otherwise.
  fetchRequestManagementReportOperatorsMock.mockReset().mockResolvedValue([])
  // Spec 0112: the site list is opted into by its own test file, empty here.
  fetchRequestManagementReportSitesMock.mockReset().mockResolvedValue([])
  fetchRequestManagementDashboardMock.mockReset().mockResolvedValue(dashboardData())
})

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function renderDashboard() {
  return render(<RequestDashboard />, { wrapper: wrapper() })
}

/** Radix tabs activate on a primary-button mousedown, not on click. */
function openTab(tab: HTMLElement) {
  fireEvent.mouseDown(tab, { button: 0, ctrlKey: false })
}

describe('RequestDashboard tabs (spec 0192)', () => {
  it('renders the overview first, then one tab per category (spec 0192 D-1, AC-002)', async () => {
    renderDashboard()

    await screen.findByRole('tab', { name: 'GOL' })
    expect(screen.getAllByRole('tab').map((tab) => tab.textContent)).toEqual(['Overview', 'GOL', 'Consulenza'])
    expect(screen.getByRole('tab', { name: 'Overview' })).toHaveAttribute('aria-selected', 'true')

    const overview = screen.getByRole('tabpanel')
    expect(within(overview).getByRole('heading', { name: 'Overall' })).toBeInTheDocument()
    expect(within(overview).getByText('N. Telefonate Effettuate', { selector: 'span[title]' })).toBeInTheDocument()
  })

  it('maps categories against indicators, marking what a category does not configure (spec 0192 AC-005)', async () => {
    renderDashboard()

    const heatmap = await screen.findByRole('table', { name: 'Categories × indicators map' })
    const [, golRow, consulenzaRow] = within(heatmap).getAllByRole('row')
    expect(within(golRow).getAllByRole('cell').map((cell) => cell.textContent)).toEqual(['8', '0'])
    // Consulenza configures no "Aule in gestione" column: not a zero, a dash.
    expect(within(consulenzaRow).getAllByRole('cell').map((cell) => cell.textContent)).toEqual([
      '4',
      '—Not configured',
    ])

    fireEvent.click(within(consulenzaRow).getByRole('button', { name: 'Consulenza' }))
    expect(screen.getByRole('tab', { name: 'Consulenza' })).toHaveAttribute('aria-selected', 'true')
  })

  it('shows a category tab with only ITS columns, zeros included, and its indicator profile (spec 0141 D-5)', async () => {
    renderDashboard()
    openTab(await screen.findByRole('tab', { name: 'GOL' }))

    const gol = screen.getByRole('tabpanel', { name: 'GOL' })
    // A 0 column is a tile like any other since rev-3.
    expect(within(gol).getByText('Aule in gestione')).toBeInTheDocument()
    expect(await within(gol).findByRole('heading', { name: 'Indicator profile' })).toBeInTheDocument()
    // `row_mode` gave this category no operator chart: no ranking either.
    expect(within(gol).queryByRole('table', { name: 'Operator ranking' })).not.toBeInTheDocument()
  })

  it('turns the operator charts into one sortable ranking (spec 0192 D-3, AC-006)', async () => {
    fetchRequestManagementReportOperatorsMock.mockResolvedValue([
      { key: '11', label: 'Ada Rossi', site_keys: [] },
      { key: 'unassigned', label: 'Unassigned', site_keys: [] },
    ])
    fetchRequestManagementDashboardMock.mockResolvedValue(
      dashboardData({
        categories: [
          {
            key: 'consulenza',
            label: 'Consulenza',
            summary: [{ key: 'phone_calls', label: 'N. Telefonate Effettuate', value: 9 }],
            charts: [
              {
                id: 'operator-consulenza-phone_calls',
                scope: 'operator',
                indicator_key: 'phone_calls',
                indicator_label: 'N. Telefonate Effettuate',
                points: [
                  { label: 'Unassigned', value: 5 },
                  { label: 'Ada Rossi', value: 3 },
                  { label: 'Zoe Bianchi', value: 1 },
                ],
              },
            ],
          },
        ],
      }),
    )

    renderDashboard()
    openTab(await screen.findByRole('tab', { name: 'Consulenza' }))

    const ranking = await screen.findByRole('table', { name: 'Operator ranking' })
    const operatorNames = () =>
      within(ranking)
        .getAllByRole('rowheader')
        .map((cell) => cell.textContent?.replace(/^[A-Z]{1,2}/, ''))
    // Highest first, "Non assegnato" last whatever its value.
    expect(operatorNames()).toEqual(['Ada Rossi', 'Zoe Bianchi', 'Unassigned'])

    const column = within(ranking).getByRole('columnheader', { name: /N\. Telefonate Effettuate/ })
    expect(column).toHaveAttribute('aria-sort', 'descending')
    fireEvent.click(within(column).getByRole('button'))
    expect(column).toHaveAttribute('aria-sort', 'ascending')
    expect(operatorNames()).toEqual(['Zoe Bianchi', 'Ada Rossi', 'Unassigned'])
  })

  it('remembers the open tab across mounts, apart per module (spec 0192 AC-002)', async () => {
    const { unmount } = renderDashboard()
    openTab(await screen.findByRole('tab', { name: 'Consulenza' }))
    unmount()

    renderDashboard()

    expect(await screen.findByRole('tab', { name: 'Consulenza' })).toHaveAttribute('aria-selected', 'true')
    expect(window.localStorage.getItem(`${REQUEST_MANAGEMENT_DOMAIN}.dashboard-tab`)).toBe('consulenza')
    expect(window.localStorage.getItem(`${ENROLLEE_MODULE.key}.dashboard-tab`)).toBeNull()
  })

  it('falls back to the overview when the remembered category is no longer returned', async () => {
    window.localStorage.setItem(`${REQUEST_MANAGEMENT_DOMAIN}.dashboard-tab`, 'retired')

    renderDashboard()

    expect(await screen.findByRole('tab', { name: 'Overview' })).toHaveAttribute('aria-selected', 'true')
  })

  it('compares a closed period with the previous one of equal length (spec 0192 D-4, AC-003/AC-004)', async () => {
    window.localStorage.setItem(
      'request-management.report-filters',
      JSON.stringify({ date_from: '2026-03-02', date_to: '2026-03-06', category_keys: ['gol'], row_mode: 'all' }),
    )
    fetchRequestManagementDashboardMock.mockImplementation((_base: string, query: { date_from?: string }) =>
      Promise.resolve(
        dashboardData({
          summary:
            query.date_from === '2026-03-02'
              ? [
                  { key: 'phone_calls', label: 'N. Telefonate Effettuate', value: 12 },
                  { key: 'unhandled_callbacks', label: 'Richiami non gestiti', value: 6 },
                ]
              : [
                  { key: 'phone_calls', label: 'N. Telefonate Effettuate', value: 8 },
                  { key: 'unhandled_callbacks', label: 'Richiami non gestiti', value: 3 },
                ],
        }),
      ),
    )

    renderDashboard()

    await waitFor(() =>
      expect(fetchRequestManagementDashboardMock).toHaveBeenCalledWith(
        '/request-management',
        expect.objectContaining({ date_from: '2026-02-25', date_to: '2026-03-01', category_keys: ['gol'] }),
      ),
    )
    const phoneTrend = await screen.findByText('+50%')
    expect(phoneTrend.closest('span')).toHaveClass('text-success')
    // More unhandled callbacks is bad news: same arrow up, destructive tone.
    expect(screen.getByText('+100%').closest('span')).toHaveClass('text-destructive')
    expect(screen.getByText('(Previous period: 8)', { exact: false })).toBeInTheDocument()
  })

  it('makes no comparison call and shows no change for an open period (spec 0192 AC-003)', async () => {
    window.localStorage.setItem(
      'request-management.report-filters',
      JSON.stringify({ date_from: '2026-03-02', date_to: '', category_keys: ['gol'], row_mode: 'all' }),
    )

    renderDashboard()

    await screen.findByRole('tab', { name: 'GOL' })
    expect(fetchRequestManagementDashboardMock).toHaveBeenCalledTimes(1)
    expect(screen.queryByText(/%$/)).not.toBeInTheDocument()
  })

  it('applies a one-click period straight to the dashboard, other filters untouched (spec 0192 AC-001)', async () => {
    renderDashboard()
    await screen.findByRole('tab', { name: 'GOL' })

    fireEvent.click(within(screen.getByRole('group', { name: 'Period' })).getByRole('button', { name: 'All time' }))

    await waitFor(() =>
      expect(fetchRequestManagementDashboardMock).toHaveBeenLastCalledWith('/request-management', {
        category_keys: ['gol', 'consulenza'],
        row_mode: 'all',
      }),
    )
    expect(within(screen.getByRole('group', { name: 'Period' })).getByRole('button', { name: 'All time' })).toHaveAttribute(
      'aria-pressed',
      'true',
    )
  })
})
