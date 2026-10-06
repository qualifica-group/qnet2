import type { ReactNode } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { RequestDashboard } from '@/features/request-management/request-dashboard'
import type { RequestDashboardData } from '@/features/request-management/dashboard-api'
import { DASHBOARD_CATEGORIES, dashboardData } from '@/features/request-management/request-dashboard-fixtures'
import { todayReportRange } from '@/features/request-management/request-report-schema'

/**
 * Spec 0107 AC-041..AC-044, AC-048: the dashboard panel, driven entirely
 * through the frozen `/request-management/report/dashboard` contract and the
 * shared branch endpoint. Since the user directive of 2026-09-08 the panel
 * OWNS the applied filters and shows them as a summary; editing them happens
 * in the sheet behind the "Filters" button, and the CSV action sits beside it
 * (`request-dashboard-filter-bar.test.tsx`). Both API modules are mocked;
 * every assertion queries by
 * accessible role/label, never `data-testid`. Dates are read from the
 * rendered fields rather than hardcoded — AC-056/AC-057 (current week)
 * already have their own dedicated, fake-timer-based coverage in
 * `request-report-schema.test.ts`.
 *
 * Spec 0192 redesigned the results as tabs (overview + one per category)
 * and added, for a closed period, a second call for the previous period of
 * equal length: assertions on the APPLIED period's request therefore pick it
 * by its dates instead of counting every call.
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
  // Spec 0109: the tests below assert the branch flow; the GA2 list is opted
  // into per test (see the operator-filter cases) and empty otherwise.
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

/** Opens the shared filter sheet, then its branch picker, and waits for the branch checkboxes. */
async function openFilters() {
  fireEvent.click(screen.getByRole('button', { name: 'Filters' }))
  fireEvent.click(await screen.findByRole('button', { name: /^Categories/ }))
  return screen.findByRole('checkbox', { name: 'GOL' })
}

/** Radix tabs activate on a primary-button mousedown, not on click. */
function openTab(tab: HTMLElement) {
  fireEvent.mouseDown(tab, { button: 0, ctrlKey: false })
}

/** The applied-filter chips of the bar (user directive 2026-09-18). */
function appliedChips() {
  return within(screen.getByRole('list', { name: 'Applied filters' }))
}

describe('RequestDashboard', () => {
  it('renders an always-visible labelled region and fetches right away (spec 0185)', async () => {
    renderDashboard()

    expect(screen.getByRole('region', { name: 'Request Management dashboard' })).toBeInTheDocument()
    await waitFor(() => expect(fetchRequestManagementDashboardMock).toHaveBeenCalled())
  })

  it('shows the applied filters as a summary instead of the controls (user directive 2026-09-08)', async () => {
    renderDashboard()

    await waitFor(() => expect(appliedChips().getByText('All categories')).toBeInTheDocument())
    expect(appliedChips().getByText('Everything')).toBeInTheDocument()
    expect(screen.queryByRole('checkbox')).not.toBeInTheDocument()
    // The CSV action sits next to the one that opens the sheet, not in the table.
    expect(screen.getByRole('button', { name: /Generate report/ })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Filters' })).toBeInTheDocument()
  })

  it('restores the filters applied in a previous session and fetches on them', async () => {
    window.localStorage.setItem(
      'request-management.report-filters',
      JSON.stringify({
        date_from: '2026-03-02',
        date_to: '2026-03-06',
        category_keys: ['consulenza'],
        row_mode: 'total_only',
      }),
    )

    renderDashboard()

    await waitFor(() =>
      expect(fetchRequestManagementDashboardMock).toHaveBeenCalledWith('/request-management', {
        date_from: '2026-03-02',
        date_to: '2026-03-06',
        category_keys: ['consulenza'],
        row_mode: 'total_only',
      }),
    )
    await waitFor(() => expect(appliedChips().getByText('Consulenza')).toBeInTheDocument())
    expect(appliedChips().getByText('Total only')).toBeInTheDocument()
  })

  it('prefills the same defaults as the CSV modal once opened (AC-043)', async () => {
    renderDashboard()

    const gol = await openFilters()
    expect(gol).toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Consulenza' })).toBeChecked()
    expect(screen.getByRole('radio', { name: 'Everything' })).toHaveAttribute('aria-checked', 'true')

    const dateFrom = screen.getByLabelText(/^From/) as HTMLInputElement
    const dateTo = screen.getByLabelText(/^To/) as HTMLInputElement
    expect(dateFrom.value).not.toBe('')
    expect(dateTo.value).not.toBe('')
    expect(dateTo.value >= dateFrom.value).toBe(true)
  })

  it('fetches the dashboard on the seeded filters, and refetches on an applied change (AC-044)', async () => {
    renderDashboard()

    const today = todayReportRange()
    await waitFor(() =>
      expect(fetchRequestManagementDashboardMock).toHaveBeenCalledWith('/request-management', {
        ...today,
        category_keys: ['gol', 'consulenza'],
        row_mode: 'all',
      }),
    )

    await openFilters()
    fireEvent.change(screen.getByLabelText(/^To/), { target: { value: '2099-01-31' } })
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    await waitFor(() =>
      expect(fetchRequestManagementDashboardMock).toHaveBeenCalledWith(
        '/request-management',
        expect.objectContaining({ date_from: today.date_from, date_to: '2099-01-31' }),
      ),
    )
  })

  it('blocks the fetch and shows the validation error when every branch is deselected (AC-044)', async () => {
    renderDashboard()
    const gol = await openFilters()
    // The applied period and, the default range being closed, its previous one.
    await waitFor(() => expect(fetchRequestManagementDashboardMock).toHaveBeenCalledTimes(2))

    fireEvent.click(gol)
    fireEvent.click(screen.getByRole('checkbox', { name: 'Consulenza' }))
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Select at least one category.')
    // An empty selection is never applied, so it never reaches the server:
    // the panel is still on the filters of the calls made so far.
    expect(fetchRequestManagementDashboardMock).toHaveBeenCalledTimes(2)
  })

  it('shows a skeleton while loading, then the summary tiles and charts (AC-048)', async () => {
    let resolveDashboard: (data: RequestDashboardData) => void = () => {}
    fetchRequestManagementDashboardMock.mockReturnValue(
      new Promise<RequestDashboardData>((resolve) => {
        resolveDashboard = resolve
      }),
    )

    const { container } = renderDashboard()

    await waitFor(() => expect(container.querySelector('[data-slot="skeleton"]')).toBeInTheDocument())

    resolveDashboard(dashboardData())

    expect(await screen.findByRole('tab', { name: 'GOL' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Overall' })).toBeInTheDocument()
    expect(container.querySelector('[data-slot="skeleton"]')).not.toBeInTheDocument()
  })

  it('shows a retryable error when the dashboard fetch fails (AC-048)', async () => {
    fetchRequestManagementDashboardMock.mockRejectedValueOnce(new Error('network')).mockResolvedValue(dashboardData())

    renderDashboard()

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('Unable to load the dashboard because of a server error.')

    fireEvent.click(screen.getByRole('button', { name: 'Retry' }))

    expect(await screen.findByRole('tab', { name: 'GOL' })).toBeInTheDocument()
  })

  it.each([
    [undefined, 'Unable to reach the server'],
    [403, 'You do not have permission to view the requests dashboard.'],
    [422, 'The applied filters are no longer valid'],
    [500, 'Unable to load the dashboard because of a server error.'],
  ])('explains a failed dashboard fetch by its HTTP status (%s)', async (status, message) => {
    const response = status === undefined ? undefined : { status, data: {}, statusText: '', headers: {}, config: {} }
    fetchRequestManagementDashboardMock.mockRejectedValue(
      new AxiosError('failed', undefined, undefined, undefined, response as never),
    )

    renderDashboard()

    expect(await screen.findByRole('alert')).toHaveTextContent(message)
  })

  it('explains that there is nothing to chart when no category has requests, without fetching', async () => {
    // A selection from an earlier session must not reach the server once no
    // category is offered any more: that was a 422 behind a generic error.
    window.localStorage.setItem(
      'request-management.report-filters',
      JSON.stringify({ date_from: '2026-03-02', date_to: '2026-03-06', category_keys: ['gol'], row_mode: 'all' }),
    )
    fetchRequestManagementReportCategoriesMock.mockResolvedValue([])

    renderDashboard()

    expect(await screen.findByRole('status')).toHaveTextContent('there are no requests in the categories')
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
    expect(fetchRequestManagementDashboardMock).not.toHaveBeenCalled()
  })

  it('never fetches on a restored category the report no longer offers', async () => {
    window.localStorage.setItem(
      'request-management.report-filters',
      JSON.stringify({ date_from: '2026-03-02', date_to: '2026-03-06', category_keys: ['retired'], row_mode: 'all' }),
    )

    renderDashboard()

    await waitFor(() => expect(fetchRequestManagementDashboardMock).toHaveBeenCalled())
    for (const [, query] of fetchRequestManagementDashboardMock.mock.calls) {
      expect(query.category_keys).not.toContain('retired')
    }
  })

  it('shows a retryable error when the category list fails to load', async () => {
    fetchRequestManagementReportCategoriesMock.mockRejectedValueOnce(new Error('network'))

    renderDashboard()

    expect(await screen.findByRole('alert')).toHaveTextContent('Unable to load categories.')
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }))
    expect(await screen.findByRole('tab', { name: 'GOL' })).toBeInTheDocument()
    expect(fetchRequestManagementDashboardMock).toHaveBeenCalled()
  })

  it('shows an explicit empty message in a section with no tile and no chart (AC-048, spec 0141 AC-010)', async () => {
    fetchRequestManagementDashboardMock.mockResolvedValue(
      dashboardData({
        categories: [{ key: 'gol', label: 'GOL', summary: [], charts: [] }],
      }),
    )

    renderDashboard()
    openTab(await screen.findByRole('tab', { name: 'GOL' }))

    // Spec 0141: a reportable category with no column configured has neither
    // tiles nor charts — both blocks fold to their own compact empty notice.
    expect(await screen.findByText('No columns configured for this category.')).toBeInTheDocument()
    expect(screen.getByText('No charts to show for this selection.')).toBeInTheDocument()
  })
})
