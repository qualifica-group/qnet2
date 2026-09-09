import type { ReactNode } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { RequestDashboardPanel } from '@/features/request-management/request-dashboard-panel'
import type { RequestReportCategory, RequestReportSite } from '@/features/request-management/report-api'
import type { RequestDashboardData } from '@/features/request-management/dashboard-api'

/**
 * Spec 0112 AC-016/AC-021 (and AC-017 end-to-end): the operational site
 * filter, driven through the panel — it owns the applied filters, so the
 * payload the charts are fetched with and the one the report file is
 * generated from are both observable here, which is the point of D-9 (one
 * normalization). Every assertion queries by accessible role/label, never
 * `data-testid`. The GA2 list is empty throughout so the site group is the
 * only narrowing group on screen.
 */

const fetchRequestManagementReportCategoriesMock = vi.fn()
const fetchRequestManagementReportOperatorsMock = vi.fn()
const fetchRequestManagementReportSitesMock = vi.fn()
const createRequestManagementReportMock = vi.fn()
vi.mock('@/features/request-management/report-api', () => ({
  fetchRequestManagementReportCategories: (...args: unknown[]) =>
    fetchRequestManagementReportCategoriesMock(...args),
  fetchRequestManagementReportOperators: (...args: unknown[]) =>
    fetchRequestManagementReportOperatorsMock(...args),
  fetchRequestManagementReportSites: (...args: unknown[]) => fetchRequestManagementReportSitesMock(...args),
  createRequestManagementReport: (...args: unknown[]) => createRequestManagementReportMock(...args),
  getRequestManagementReport: vi.fn(),
  downloadRequestManagementReport: vi.fn(),
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, isLoading: false }),
}))

const fetchRequestManagementDashboardMock = vi.fn()
vi.mock('@/features/request-management/dashboard-api', () => ({
  fetchRequestManagementDashboard: (...args: unknown[]) => fetchRequestManagementDashboardMock(...args),
}))

const CATEGORIES: RequestReportCategory[] = [{ key: 'gol', label: 'GOL' }]

/** Composed addresses, exactly as the backend serves them (D-8): never i18n keys. */
const SITES: RequestReportSite[] = [
  { key: '3', label: 'Via Roma 1 - Frattamaggiore' },
  { key: '7', label: 'Corso Italia 9 - Napoli' },
]

const DASHBOARD_DATA: RequestDashboardData = {
  applied: {
    date_from: '2026-09-07',
    date_to: '2026-09-11',
    category_keys: ['gol'],
    row_mode: 'all',
    operator_keys: null,
    site_keys: null,
  },
  summary: [],
  categories: [],
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  window.localStorage.clear()
  fetchRequestManagementReportCategoriesMock.mockReset().mockResolvedValue(CATEGORIES)
  fetchRequestManagementReportOperatorsMock.mockReset().mockResolvedValue([])
  fetchRequestManagementReportSitesMock.mockReset().mockResolvedValue(SITES)
  fetchRequestManagementDashboardMock.mockReset().mockResolvedValue(DASHBOARD_DATA)
  createRequestManagementReportMock.mockReset().mockResolvedValue({
    id: 1,
    status: 'processing',
    file_name: 'r.csv',
    created_at: '2026-09-09T10:00:00Z',
  })
})

/** One QueryClient per test (never per render), so no cache leaks between cases. */
function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

/** Mounts the open panel and opens the filter sheet. */
function renderPanel() {
  render(<RequestDashboardPanel isOpen />, { wrapper: wrapper() })
}

async function openFilters() {
  renderPanel()
  await screen.findByText(/1\/1 categories/)
  fireEvent.click(screen.getByRole('button', { name: 'Filters' }))
}

/** The query the charts were last fetched with. */
function lastDashboardQuery(): Record<string, unknown> {
  const calls = fetchRequestManagementDashboardMock.mock.calls

  return calls[calls.length - 1][0] as Record<string, unknown>
}

function apply() {
  fireEvent.click(screen.getByRole('button', { name: 'Apply' }))
}

function pickRowMode(name: string) {
  fireEvent.click(screen.getByRole('radio', { name }))
}

describe('report site filter (spec 0112)', () => {
  // -------------------------------------------------------------------------
  // AC-016 — the group follows the row mode, the selection does not
  // -------------------------------------------------------------------------

  it('shows the site group for "operators only" and "everything", never for "total only" (AC-016)', async () => {
    await openFilters()

    expect(await screen.findByRole('checkbox', { name: 'Via Roma 1 - Frattamaggiore' })).toBeInTheDocument()
    expect(screen.getByRole('checkbox', { name: 'Corso Italia 9 - Napoli' })).toBeInTheDocument()

    pickRowMode('Total only')
    await waitFor(() =>
      expect(screen.queryByRole('checkbox', { name: 'Via Roma 1 - Frattamaggiore' })).not.toBeInTheDocument(),
    )

    pickRowMode('Operators only')
    expect(await screen.findByRole('checkbox', { name: 'Via Roma 1 - Frattamaggiore' })).toBeInTheDocument()
  })

  it('keeps the selection while the group is hidden and restores it on the way back (AC-016)', async () => {
    await openFilters()
    await screen.findByRole('checkbox', { name: 'Corso Italia 9 - Napoli' })

    fireEvent.click(screen.getByRole('checkbox', { name: 'Corso Italia 9 - Napoli' }))
    pickRowMode('Total only')
    apply()

    await waitFor(() => expect(lastDashboardQuery().row_mode).toBe('total_only'))
    expect(lastDashboardQuery()).not.toHaveProperty('site_keys')

    fireEvent.click(screen.getByRole('button', { name: 'Filters' }))
    pickRowMode('Everything')

    // The selection was never cleared, only hidden.
    expect(await screen.findByRole('checkbox', { name: 'Via Roma 1 - Frattamaggiore' })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Corso Italia 9 - Napoli' })).not.toBeChecked()
  })

  // -------------------------------------------------------------------------
  // AC-017 (end to end) — one normalization feeds charts AND file
  // -------------------------------------------------------------------------

  it('starts with every site selected and omits site_keys from the payload (AC-017)', async () => {
    await openFilters()

    for (const site of SITES) {
      expect(await screen.findByRole('checkbox', { name: site.label })).toBeChecked()
    }

    await waitFor(() => expect(lastDashboardQuery()).not.toHaveProperty('site_keys'))
  })

  it('sends the remaining keys to the charts and generates the file from the same payload (AC-017)', async () => {
    await openFilters()
    await screen.findByRole('checkbox', { name: 'Corso Italia 9 - Napoli' })

    fireEvent.click(screen.getByRole('checkbox', { name: 'Corso Italia 9 - Napoli' }))
    apply()

    await waitFor(() => expect(lastDashboardQuery().site_keys).toEqual(['3']))

    // Radix opens the menu on pointerdown, not click.
    fireEvent.pointerDown(screen.getByRole('button', { name: /Generate report/ }), {
      button: 0,
      ctrlKey: false,
    })
    fireEvent.click(await screen.findByRole('menuitem', { name: 'CSV' }))

    await waitFor(() =>
      expect(createRequestManagementReportMock).toHaveBeenCalledWith(
        expect.objectContaining({ site_keys: ['3'], format: 'csv' }),
      ),
    )
  })

  it('blocks apply and fires no request when every site is deselected (AC-020)', async () => {
    await openFilters()
    await screen.findByRole('checkbox', { name: 'Corso Italia 9 - Napoli' })
    const callsBefore = fetchRequestManagementDashboardMock.mock.calls.length

    for (const site of SITES) {
      fireEvent.click(screen.getByRole('checkbox', { name: site.label }))
    }
    apply()

    expect(await screen.findByText('Select at least one site.')).toBeInTheDocument()
    expect(fetchRequestManagementDashboardMock.mock.calls).toHaveLength(callsBefore)
  })

  // -------------------------------------------------------------------------
  // AC-021 — the group's own loading and error states
  // -------------------------------------------------------------------------

  it('announces its own loading state while the site list is in flight (AC-021)', async () => {
    fetchRequestManagementReportSitesMock.mockReturnValue(new Promise<RequestReportSite[]>(() => {}))

    await openFilters()

    expect(await screen.findByText('Loading sites…')).toBeInTheDocument()
  })

  it('reports a failed site list as an alert, and only while the group is visible (AC-021)', async () => {
    fetchRequestManagementReportSitesMock.mockRejectedValue(new Error('boom'))

    await openFilters()

    const alert = await screen.findByText('Unable to load sites. Please try again.')
    expect(alert).toHaveAttribute('role', 'alert')

    pickRowMode('Total only')
    await waitFor(() =>
      expect(screen.queryByText('Unable to load sites. Please try again.')).not.toBeInTheDocument(),
    )
  })

  it('renders the site labels verbatim, never through i18next (AC-021)', async () => {
    fetchRequestManagementReportSitesMock.mockResolvedValue([{ key: '9', label: 'requestManagement.report' }])

    await openFilters()

    expect(await screen.findByRole('checkbox', { name: 'requestManagement.report' })).toBeInTheDocument()
  })
})
