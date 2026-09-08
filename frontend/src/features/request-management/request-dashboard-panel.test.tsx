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
 * shared branch endpoint. Both API modules are mocked; every assertion
 * queries by accessible role/label, never `data-testid`. Dates are read from
 * the rendered fields rather than hardcoded — AC-056/AC-057 (current week)
 * already have their own dedicated, fake-timer-based coverage in
 * `request-report-schema.test.ts`/`request-report-dialog.test.tsx`.
 */

const fetchRequestManagementReportCategoriesMock = vi.fn()
vi.mock('@/features/request-management/report-api', () => ({
  fetchRequestManagementReportCategories: (...args: unknown[]) =>
    fetchRequestManagementReportCategoriesMock(...args),
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

describe('RequestDashboardPanel', () => {
  it('issues no request while the panel is closed (AC-042)', () => {
    renderPanel(false)

    expect(fetchRequestManagementReportCategoriesMock).not.toHaveBeenCalled()
    expect(fetchRequestManagementDashboardMock).not.toHaveBeenCalled()
  })

  it('prefills the same defaults as the CSV modal once opened (AC-043)', async () => {
    renderPanel(true)

    const gol = await screen.findByRole('checkbox', { name: 'GOL' })
    expect(gol).toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Consulenza' })).toBeChecked()
    expect(screen.getByRole('radio', { name: 'Everything' })).toHaveAttribute('aria-checked', 'true')

    const dateFrom = screen.getByLabelText(/^From/) as HTMLInputElement
    const dateTo = screen.getByLabelText(/^To/) as HTMLInputElement
    expect(dateFrom.value).not.toBe('')
    expect(dateTo.value).not.toBe('')
    expect(dateTo.value >= dateFrom.value).toBe(true)
  })

  it('fetches the dashboard once the filters settle valid, and refetches on a filter change (AC-044)', async () => {
    renderPanel(true)
    await screen.findByRole('checkbox', { name: 'GOL' })

    await waitFor(() => expect(fetchRequestManagementDashboardMock).toHaveBeenCalledTimes(1))
    const initialQuery = fetchRequestManagementDashboardMock.mock.calls[0][0]
    expect(initialQuery).toEqual({
      date_from: expect.any(String),
      date_to: expect.any(String),
      category_keys: ['gol', 'consulenza'],
      row_mode: 'all',
    })

    fireEvent.change(screen.getByLabelText(/^To/), { target: { value: '2099-01-31' } })

    await waitFor(() => expect(fetchRequestManagementDashboardMock).toHaveBeenCalledTimes(2))
    expect(fetchRequestManagementDashboardMock).toHaveBeenLastCalledWith(
      expect.objectContaining({ date_from: initialQuery.date_from, date_to: '2099-01-31' }),
    )
  })

  it('blocks the fetch and shows the validation error when every branch is deselected (AC-044)', async () => {
    renderPanel(true)
    const gol = await screen.findByRole('checkbox', { name: 'GOL' })
    await waitFor(() => expect(fetchRequestManagementDashboardMock).toHaveBeenCalled())

    fireEvent.click(gol)
    fireEvent.click(screen.getByRole('checkbox', { name: 'Consulenza' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Select at least one category.')
    // Deselecting one branch at a time crosses a legitimately valid
    // intermediate state (one branch still checked), which the query key
    // correctly refetches on its own — AC-044's actual requirement is that
    // the FINAL, fully-empty selection never reaches the server: no call was
    // ever made with an empty `category_keys`, at any point in the sequence.
    await waitFor(() =>
      expect(
        fetchRequestManagementDashboardMock.mock.calls.every((call) => call[0].category_keys.length > 0),
      ).toBe(true),
    )
  })

  it('shows a skeleton while loading, then the summary tiles and charts (AC-048)', async () => {
    let resolveDashboard: (data: RequestDashboardData) => void = () => {}
    fetchRequestManagementDashboardMock.mockReturnValue(
      new Promise<RequestDashboardData>((resolve) => {
        resolveDashboard = resolve
      }),
    )

    const { container } = renderPanel(true)
    await screen.findByRole('checkbox', { name: 'GOL' })

    await waitFor(() => expect(container.querySelector('[data-slot="skeleton"]')).toBeInTheDocument())

    resolveDashboard(dashboardData())

    expect(await screen.findByRole('heading', { name: 'N. Telefonate Effettuate' })).toBeInTheDocument()
    expect(container.querySelector('[data-slot="skeleton"]')).not.toBeInTheDocument()
  })

  it('shows a retryable error when the dashboard fetch fails (AC-048)', async () => {
    fetchRequestManagementDashboardMock.mockRejectedValueOnce(new Error('network')).mockResolvedValue(dashboardData())

    renderPanel(true)
    await screen.findByRole('checkbox', { name: 'GOL' })

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
