import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { DashboardScreen } from '@/features/dashboard/dashboard-screen'

/**
 * REQUIREMENT CHANGED (user decision 2026-11-09, replaces spec 0151 AC-011): the
 * home is an empty page that reads no data, whatever the user's permissions.
 */

const fetchDashboardTaskCountersMock = vi.fn()
vi.mock('@/features/dashboard/api', () => ({
  fetchDashboardTaskCounters: () => fetchDashboardTaskCountersMock(),
}))

const fetchTimeEntriesOverviewMock = vi.fn()
const fetchTimeEntriesPulseMock = vi.fn()
vi.mock('@/features/time-entries/api', () => ({
  fetchTimeEntriesOverview: (...args: unknown[]) => fetchTimeEntriesOverviewMock(...args),
  fetchTimeEntriesPulse: (...args: unknown[]) => fetchTimeEntriesPulseMock(...args),
}))

const getAbsenceSummaryMock = vi.fn()
vi.mock('@/features/absences/api', () => ({ getAbsenceSummary: () => getAbsenceSummaryMock() }))

const fetchModuleStatsMock = vi.fn()
vi.mock('@/features/stats/api', () => ({
  moduleStatsQueryKey: (domain: string) => ['stats', domain],
  fetchModuleStats: (domain: string) => fetchModuleStatsMock(domain),
}))

beforeEach(() => {
  vi.clearAllMocks()
})

function renderScreen() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <DashboardScreen />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

describe('DashboardScreen', () => {
  it('shows no breadcrumb on the home', () => {
    renderScreen()

    expect(screen.queryByRole('navigation', { name: 'breadcrumb' })).not.toBeInTheDocument()
  })

  it('renders no block and issues no data request', () => {
    renderScreen()

    expect(screen.queryByRole('heading')).not.toBeInTheDocument()
    expect(fetchDashboardTaskCountersMock).not.toHaveBeenCalled()
    expect(fetchTimeEntriesOverviewMock).not.toHaveBeenCalled()
    expect(fetchTimeEntriesPulseMock).not.toHaveBeenCalled()
    expect(getAbsenceSummaryMock).not.toHaveBeenCalled()
    expect(fetchModuleStatsMock).not.toHaveBeenCalled()
  })
})
