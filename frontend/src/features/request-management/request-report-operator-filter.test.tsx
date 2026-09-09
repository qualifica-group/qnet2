import type { ReactNode } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { RequestDashboardPanel } from '@/features/request-management/request-dashboard-panel'
import type { RequestReportCategory, RequestReportOperator } from '@/features/request-management/report-api'
import type { RequestDashboardData } from '@/features/request-management/dashboard-api'

/**
 * Spec 0109 AC-040..AC-049: the GA2 operator filter, driven end-to-end
 * through the panel — it owns the applied filters, so the payload the charts
 * are fetched with and the one the report file is generated from are both
 * observable here, which is the whole point of D-9 (one normalization).
 * Every assertion queries by accessible role/label, never `data-testid`.
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

/** Two named GA2 plus "Non assegnato", exactly as the backend serves it (D-6). */
const OPERATORS: RequestReportOperator[] = [
  { key: '7', label: 'Ada Rossi' },
  { key: '9', label: 'Zoe Bianchi' },
  { key: 'unassigned', label: 'Unassigned' },
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
  fetchRequestManagementReportOperatorsMock.mockReset().mockResolvedValue(OPERATORS)
  // Spec 0112: the site group has its own test file; an empty list keeps it
  // out of the way of the operator-group assertions here.
  fetchRequestManagementReportSitesMock.mockReset().mockResolvedValue([])
  fetchRequestManagementDashboardMock.mockReset().mockResolvedValue(DASHBOARD_DATA)
  createRequestManagementReportMock.mockReset().mockResolvedValue({
    id: 1,
    status: 'processing',
    file_name: 'r.csv',
    created_at: '2026-09-08T10:00:00Z',
  })
})

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

/** Mounts the open panel and opens the filter sheet on its loaded lists. */
async function openFilters() {
  render(<RequestDashboardPanel isOpen />, { wrapper: wrapper() })
  await screen.findByText(/1\/1 categories/)
  fireEvent.click(screen.getByRole('button', { name: 'Filters' }))

  return screen.findByRole('checkbox', { name: 'Ada Rossi' })
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

describe('report operator filter (spec 0109)', () => {
  // -------------------------------------------------------------------------
  // AC-040 — the group appears only where operator rows exist
  // -------------------------------------------------------------------------

  it('shows the operator group for "operators only" and "everything", never for "total only" (AC-040)', async () => {
    await openFilters()

    expect(screen.getByRole('checkbox', { name: 'Zoe Bianchi' })).toBeInTheDocument()

    pickRowMode('Total only')
    await waitFor(() => expect(screen.queryByRole('checkbox', { name: 'Ada Rossi' })).not.toBeInTheDocument())

    pickRowMode('Operators only')
    expect(await screen.findByRole('checkbox', { name: 'Ada Rossi' })).toBeInTheDocument()
  })

  it('offers "Non assegnato" as a selectable GA2, labelled by the server (AC-040)', async () => {
    await openFilters()

    // Rendered verbatim: it is the report's own catalogue entry, not an
    // i18next string of the client's.
    expect(screen.getByRole('checkbox', { name: 'Unassigned' })).toBeInTheDocument()
  })

  // -------------------------------------------------------------------------
  // AC-041 / AC-042 — seeding, tri-state, and the "all means omitted" rule
  // -------------------------------------------------------------------------

  it('starts with every operator selected and omits operator_keys from the payload (AC-042)', async () => {
    await openFilters()

    for (const name of ['Ada Rossi', 'Zoe Bianchi', 'Unassigned']) {
      expect(screen.getByRole('checkbox', { name })).toBeChecked()
    }

    // D-2: "every operator" travels as an ABSENT field, never as a frozen list.
    await waitFor(() => expect(lastDashboardQuery()).not.toHaveProperty('operator_keys'))
  })

  it('drives the select-all control tri-state and the counter (AC-041)', async () => {
    await openFilters()

    const selectAll = screen.getAllByRole('checkbox', { name: 'Select all' })[1]
    expect(selectAll).toHaveAttribute('data-state', 'checked')
    expect(screen.getByText('3/3')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('checkbox', { name: 'Zoe Bianchi' }))

    await waitFor(() => expect(selectAll).toHaveAttribute('data-state', 'indeterminate'))
    expect(screen.getByText('2/3')).toBeInTheDocument()
  })

  // -------------------------------------------------------------------------
  // AC-043 / AC-046 — a partial selection reaches BOTH consumers
  // -------------------------------------------------------------------------

  it('sends the remaining keys once an operator is deselected (AC-043)', async () => {
    await openFilters()

    fireEvent.click(screen.getByRole('checkbox', { name: 'Zoe Bianchi' }))
    fireEvent.click(screen.getByRole('checkbox', { name: 'Unassigned' }))
    apply()

    await waitFor(() => expect(lastDashboardQuery().operator_keys).toEqual(['7']))
  })

  it('generates the report file on the very same selection as the charts (AC-046)', async () => {
    await openFilters()

    fireEvent.click(screen.getByRole('checkbox', { name: 'Ada Rossi' }))
    apply()

    await waitFor(() => expect(lastDashboardQuery().operator_keys).toEqual(['9', 'unassigned']))

    // Radix opens the menu on pointerdown, not click (same helper the filter
    // bar's own test uses).
    fireEvent.pointerDown(screen.getByRole('button', { name: /Generate report/ }), {
      button: 0,
      ctrlKey: false,
    })
    fireEvent.click(await screen.findByRole('menuitem', { name: 'CSV' }))

    await waitFor(() =>
      expect(createRequestManagementReportMock).toHaveBeenCalledWith(
        expect.objectContaining({ operator_keys: ['9', 'unassigned'], format: 'csv' }),
      ),
    )
  })

  // -------------------------------------------------------------------------
  // AC-044 — an empty selection is a client error, never a request
  // -------------------------------------------------------------------------

  it('blocks apply and fires no request when every operator is deselected (AC-044)', async () => {
    await openFilters()
    const callsBefore = fetchRequestManagementDashboardMock.mock.calls.length

    for (const name of ['Ada Rossi', 'Zoe Bianchi', 'Unassigned']) {
      fireEvent.click(screen.getByRole('checkbox', { name }))
    }
    apply()

    expect(await screen.findByText('Select at least one operator.')).toBeInTheDocument()
    expect(fetchRequestManagementDashboardMock.mock.calls).toHaveLength(callsBefore)
  })

  // -------------------------------------------------------------------------
  // AC-045 — "total only" drops the field but keeps the selection
  // -------------------------------------------------------------------------

  it('drops operator_keys under "total only" and restores the selection on the way back (AC-045)', async () => {
    await openFilters()

    fireEvent.click(screen.getByRole('checkbox', { name: 'Zoe Bianchi' }))
    fireEvent.click(screen.getByRole('checkbox', { name: 'Unassigned' }))
    pickRowMode('Total only')
    apply()

    await waitFor(() => expect(lastDashboardQuery().row_mode).toBe('total_only'))
    expect(lastDashboardQuery()).not.toHaveProperty('operator_keys')

    fireEvent.click(screen.getByRole('button', { name: 'Filters' }))
    pickRowMode('Operators only')

    // The selection was never cleared, only hidden.
    expect(await screen.findByRole('checkbox', { name: 'Ada Rossi' })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Zoe Bianchi' })).not.toBeChecked()

    apply()
    await waitFor(() => expect(lastDashboardQuery().operator_keys).toEqual(['7']))
  })

  // -------------------------------------------------------------------------
  // AC-047 — a preference stored before this spec still loads
  // -------------------------------------------------------------------------

  it('keeps a stored preference written without operator_keys, seeding every operator (AC-047)', async () => {
    window.localStorage.setItem(
      'request-management.report-filters',
      JSON.stringify({
        date_from: '2026-03-02',
        date_to: '2026-03-06',
        category_keys: ['gol'],
        row_mode: 'all',
      }),
    )

    await openFilters()

    // The dates and branches the operator was working on survive...
    expect(screen.getByLabelText(/^From/)).toHaveValue('2026-03-02')
    // ...and the field that did not exist back then seeds to "everything".
    expect(screen.getByRole('checkbox', { name: 'Ada Rossi' })).toBeChecked()
    expect(lastDashboardQuery()).not.toHaveProperty('operator_keys')
  })
})
