import type { ReactNode } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
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
const fetchRequestManagementReportOperatorsMock = vi.fn()
vi.mock('@/features/request-management/report-api', () => ({
  fetchRequestManagementReportCategories: (...args: unknown[]) =>
    fetchRequestManagementReportCategoriesMock(...args),
  fetchRequestManagementReportOperators: (...args: unknown[]) =>
    fetchRequestManagementReportOperatorsMock(...args),
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
    applied: {
      date_from: '2026-09-07',
      date_to: '2026-09-11',
      category_keys: ['gol', 'consulenza'],
      row_mode: 'all',
      operator_keys: null,
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

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  // The applied filters are persisted (user directive 2026-09-08): without this
  // one test's selection would seed the next one's mount.
  window.localStorage.clear()
  fetchRequestManagementReportCategoriesMock.mockReset().mockResolvedValue(CATEGORIES)
  // Spec 0109: the tests below assert the branch flow; the GA2 list is opted
  // into per test (see the operator-filter cases) and empty otherwise.
  fetchRequestManagementReportOperatorsMock.mockReset().mockResolvedValue([])
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

    expect(await screen.findByRole('heading', { name: 'GOL' })).toBeInTheDocument()
    expect(
      within(screen.getByRole('region', { name: 'GOL' })).getByRole('heading', { name: 'Charts (1)' }),
    ).toBeInTheDocument()
    expect(container.querySelector('[data-slot="skeleton"]')).not.toBeInTheDocument()
  })

  it('shows a retryable error when the dashboard fetch fails (AC-048)', async () => {
    fetchRequestManagementDashboardMock.mockRejectedValueOnce(new Error('network')).mockResolvedValue(dashboardData())

    renderPanel(true)

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('Unable to load the dashboard.')

    fireEvent.click(screen.getByRole('button', { name: 'Retry' }))

    expect(await screen.findByRole('heading', { name: 'GOL' })).toBeInTheDocument()
  })

  it('shows an explicit empty message in a section with no chart (AC-048)', async () => {
    fetchRequestManagementDashboardMock.mockResolvedValue(
      dashboardData({
        categories: [{ key: 'gol', label: 'GOL', summary: [], charts: [] }],
      }),
    )

    renderPanel(true)

    expect(await screen.findByText('No charts to show for this selection.')).toBeInTheDocument()
  })

  it('renders a section per category, with every indicator tile including the zeros (rev-3 D-10/D-11)', async () => {
    renderPanel(true)

    // Overall tiles first, then one section per selected category.
    const headings = await screen.findAllByRole('heading', { level: 2 })
    expect(headings.map((heading) => heading.textContent)).toEqual(['Overall', 'GOL', 'Consulenza'])

    const gol = screen.getByRole('region', { name: 'GOL' })
    // A 0 column is a tile like any other since rev-3.
    expect(within(gol).getByText('Aule in gestione')).toBeInTheDocument()

    // Charts start folded, so opening the block is what reveals their titles.
    fireEvent.click(within(gol).getByRole('button', { name: 'Charts (1)' }))
    expect(await within(gol).findByRole('heading', { name: 'Indicators' })).toBeInTheDocument()

    // An operator chart keeps its own indicator as the title, the category
    // being the section it sits in.
    const consulenza = screen.getByRole('region', { name: 'Consulenza' })
    fireEvent.click(within(consulenza).getByRole('button', { name: 'Charts (1)' }))
    expect(
      await within(consulenza).findByRole('heading', { name: 'N. Telefonate Effettuate' }),
    ).toBeInTheDocument()
  })

  it('opens on the tiles and keeps the charts folded away (user directive 2026-09-08)', async () => {
    renderPanel(true)

    const gol = await screen.findByRole('region', { name: 'GOL' })

    expect(within(gol).getByRole('button', { name: 'Summary' })).toHaveAttribute('aria-expanded', 'true')
    expect(within(gol).getByRole('button', { name: 'Charts (1)' })).toHaveAttribute('aria-expanded', 'false')
    expect(within(gol).getByText('N. Telefonate Effettuate')).toBeInTheDocument() // the tile
    expect(within(gol).queryByRole('heading', { name: 'Indicators' })).not.toBeInTheDocument()
  })

  it('persists every collapse toggle and restores it on the next mount (user directive 2026-09-08)', async () => {
    const { unmount } = renderPanel(true)

    const gol = await screen.findByRole('region', { name: 'GOL' })
    fireEvent.click(within(gol).getByRole('button', { name: 'Summary' })) // fold the tiles
    fireEvent.click(within(gol).getByRole('button', { name: 'GOL' })) // fold the whole section

    await waitFor(() =>
      expect(screen.getByRole('button', { name: 'GOL' })).toHaveAttribute('aria-expanded', 'false'),
    )
    unmount()

    renderPanel(true)

    const restored = await screen.findByRole('region', { name: 'GOL' })
    expect(within(restored).getByRole('button', { name: 'GOL' })).toHaveAttribute('aria-expanded', 'false')

    // Re-opening the section shows the tiles still folded from the last session.
    fireEvent.click(within(restored).getByRole('button', { name: 'GOL' }))
    expect(await within(restored).findByRole('button', { name: 'Summary' })).toHaveAttribute(
      'aria-expanded',
      'false',
    )
  })
})
