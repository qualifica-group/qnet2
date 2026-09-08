import type { ReactNode } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { RequestDashboardPanel } from '@/features/request-management/request-dashboard-panel'
import type { RequestReportCategory } from '@/features/request-management/report-api'
import type { RequestDashboardData } from '@/features/request-management/dashboard-api'

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
 */

const fetchRequestManagementReportCategoriesMock = vi.fn()
vi.mock('@/features/request-management/report-api', () => ({
  fetchRequestManagementReportCategories: (...args: unknown[]) =>
    fetchRequestManagementReportCategoriesMock(...args),
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

const CATEGORIES: RequestReportCategory[] = [
  { key: 'gol', label: 'GOL' },
  { key: 'consulenza', label: 'Consulenza' },
]

function dashboardData(overrides: Partial<RequestDashboardData> = {}): RequestDashboardData {
  return {
    applied: { date_from: '2026-09-07', date_to: '2026-09-11', category_keys: ['gol', 'consulenza'], row_mode: 'all' },
    summary: [{ key: 'phone_calls', label: 'N. Telefonate Effettuate', value: 12 }],
    charts: [
      {
        id: 'category-phone_calls',
        scope: 'category',
        category_key: null,
        category_label: null,
        indicator_key: 'phone_calls',
        indicator_label: 'N. Telefonate Effettuate',
        points: [
          { label: 'GOL', value: 8 },
          { label: 'Consulenza', value: 4 },
        ],
      },
    ],
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  // The applied filters are persisted (user directive 2026-09-08): without this
  // one test's selection would seed the next one's mount.
  window.localStorage.clear()
  fetchRequestManagementReportCategoriesMock.mockReset().mockResolvedValue(CATEGORIES)
  fetchRequestManagementDashboardMock.mockReset().mockResolvedValue(dashboardData())
})

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function renderPanel(isOpen: boolean) {
  return render(<RequestDashboardPanel isOpen={isOpen} />, { wrapper: wrapper() })
}

/** Opens the shared filter sheet and waits for its branch checkboxes. */
async function openFilters() {
  fireEvent.click(screen.getByRole('button', { name: 'Filters' }))
  return screen.findByRole('checkbox', { name: 'GOL' })
}

describe('RequestDashboardPanel', () => {
  it('issues no request while the panel is closed (AC-042)', () => {
    renderPanel(false)

    expect(fetchRequestManagementReportCategoriesMock).not.toHaveBeenCalled()
    expect(fetchRequestManagementDashboardMock).not.toHaveBeenCalled()
  })

  it('shows the applied filters as a summary instead of the controls (user directive 2026-09-08)', async () => {
    renderPanel(true)

    await waitFor(() => expect(screen.getByText(/2\/2 categories/)).toHaveTextContent('Everything'))
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

    renderPanel(true)

    await waitFor(() =>
      expect(fetchRequestManagementDashboardMock).toHaveBeenCalledWith({
        date_from: '2026-03-02',
        date_to: '2026-03-06',
        category_keys: ['consulenza'],
        row_mode: 'total_only',
      }),
    )
    expect(await screen.findByText(/1\/2 categories/)).toHaveTextContent('Total only')
  })

  it('prefills the same defaults as the CSV modal once opened (AC-043)', async () => {
    renderPanel(true)

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
    renderPanel(true)

    await waitFor(() => expect(fetchRequestManagementDashboardMock).toHaveBeenCalledTimes(1))
    const initialQuery = fetchRequestManagementDashboardMock.mock.calls[0][0]
    expect(initialQuery).toEqual({
      date_from: expect.any(String),
      date_to: expect.any(String),
      category_keys: ['gol', 'consulenza'],
      row_mode: 'all',
    })

    await openFilters()
    fireEvent.change(screen.getByLabelText(/^To/), { target: { value: '2099-01-31' } })
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    await waitFor(() => expect(fetchRequestManagementDashboardMock).toHaveBeenCalledTimes(2))
    expect(fetchRequestManagementDashboardMock).toHaveBeenLastCalledWith(
      expect.objectContaining({ date_from: initialQuery.date_from, date_to: '2099-01-31' }),
    )
  })

  it('blocks the fetch and shows the validation error when every branch is deselected (AC-044)', async () => {
    renderPanel(true)
    const gol = await openFilters()
    await waitFor(() => expect(fetchRequestManagementDashboardMock).toHaveBeenCalledTimes(1))

    fireEvent.click(gol)
    fireEvent.click(screen.getByRole('checkbox', { name: 'Consulenza' }))
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Select at least one category.')
    // An empty selection is never applied, so it never reaches the server:
    // the panel is still on the filters of the only call made so far.
    expect(fetchRequestManagementDashboardMock).toHaveBeenCalledTimes(1)
  })

  it('shows a skeleton while loading, then the summary tiles and charts (AC-048)', async () => {
    let resolveDashboard: (data: RequestDashboardData) => void = () => {}
    fetchRequestManagementDashboardMock.mockReturnValue(
      new Promise<RequestDashboardData>((resolve) => {
        resolveDashboard = resolve
      }),
    )

    const { container } = renderPanel(true)

    await waitFor(() => expect(container.querySelector('[data-slot="skeleton"]')).toBeInTheDocument())

    resolveDashboard(dashboardData())

    expect(await screen.findByRole('heading', { name: 'N. Telefonate Effettuate' })).toBeInTheDocument()
    expect(container.querySelector('[data-slot="skeleton"]')).not.toBeInTheDocument()
  })

  it('shows a retryable error when the dashboard fetch fails (AC-048)', async () => {
    fetchRequestManagementDashboardMock.mockRejectedValueOnce(new Error('network')).mockResolvedValue(dashboardData())

    renderPanel(true)

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('Unable to load the dashboard.')

    fireEvent.click(screen.getByRole('button', { name: 'Retry' }))

    expect(await screen.findByRole('heading', { name: 'N. Telefonate Effettuate' })).toBeInTheDocument()
  })

  it('shows an explicit empty message when no chart survives (AC-048)', async () => {
    fetchRequestManagementDashboardMock.mockResolvedValue(dashboardData({ charts: [] }))

    renderPanel(true)

    expect(await screen.findByText('No charts to show for this selection.')).toBeInTheDocument()
  })
})
