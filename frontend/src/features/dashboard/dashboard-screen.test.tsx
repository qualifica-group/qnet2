import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { DashboardScreen } from '@/features/dashboard/dashboard-screen'
import type { DashboardTaskCounters } from '@/features/dashboard/types'
import type { ModuleStats } from '@/features/stats/types'

/**
 * Spec 0151 AC-011: each block is present iff its `frontend_gates` permission
 * is granted, no request fires for a hidden block, and the empty state covers
 * the "every gate closed" case.
 */

vi.mock('@/components/page-header', () => ({
  PageHeader: () => <div />,
}))

let grantedPermissions: string[] = []
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => grantedPermissions.includes(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

const fetchDashboardTaskCountersMock = vi.fn<() => Promise<DashboardTaskCounters>>()
vi.mock('@/features/dashboard/api', () => ({
  fetchDashboardTaskCounters: () => fetchDashboardTaskCountersMock(),
}))

const fetchTimeEntriesOverviewMock = vi.fn()
const fetchTimeEntriesPulseMock = vi.fn()
vi.mock('@/features/time-entries/api', () => ({
  fetchTimeEntriesOverview: (...args: unknown[]) => fetchTimeEntriesOverviewMock(...args),
  fetchTimeEntriesPulse: (...args: unknown[]) => fetchTimeEntriesPulseMock(...args),
}))

const fetchModuleStatsMock = vi.fn<(domain: string) => Promise<ModuleStats>>()
vi.mock('@/features/stats/api', () => ({
  moduleStatsQueryKey: (domain: string) => ['stats', domain],
  fetchModuleStats: (domain: string) => fetchModuleStatsMock(domain),
}))

const COUNTERS: DashboardTaskCounters = {
  not_completed: { count: 1, total_minutes: 0 },
  assigned_to_me: { count: 1, total_minutes: 0 },
  assigned_by_me: { count: 1, total_minutes: 0, to_validate: { count: 0, total_minutes: 0 } },
  created_by_me: { count: 1, total_minutes: 0 },
  observed_by_me: { count: 1, total_minutes: 0 },
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  grantedPermissions = []
  fetchDashboardTaskCountersMock.mockReset().mockResolvedValue(COUNTERS)
  fetchTimeEntriesOverviewMock.mockReset().mockResolvedValue({})
  fetchTimeEntriesPulseMock.mockReset().mockResolvedValue({})
  fetchModuleStatsMock.mockReset().mockResolvedValue({ widgets: [] })
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

describe('DashboardScreen (AC-011)', () => {
  it('shows the empty state and issues no request when every gate is closed', async () => {
    renderScreen()

    expect(await screen.findByText('No content available')).toBeInTheDocument()
    expect(screen.queryByText('Activities to complete')).not.toBeInTheDocument()
    expect(screen.queryByText('Time tracking')).not.toBeInTheDocument()
    expect(fetchDashboardTaskCountersMock).not.toHaveBeenCalled()
    expect(fetchTimeEntriesOverviewMock).not.toHaveBeenCalled()
    expect(fetchModuleStatsMock).not.toHaveBeenCalled()
  })

  it('shows only the blocks gated by tasks.viewAny when it is the only permission granted', async () => {
    grantedPermissions = ['tasks.viewAny']
    renderScreen()

    expect(await screen.findByText('Activities to complete')).toBeInTheDocument()
    expect(fetchDashboardTaskCountersMock).toHaveBeenCalledTimes(1)
    expect(fetchModuleStatsMock).toHaveBeenCalledWith('tasks')
    expect(fetchModuleStatsMock).toHaveBeenCalledTimes(1)
    expect(screen.queryByText('No content available')).not.toBeInTheDocument()
    expect(screen.queryByText('Time tracking')).not.toBeInTheDocument()
    expect(screen.queryByText('Opportunities')).not.toBeInTheDocument()
    expect(fetchTimeEntriesOverviewMock).not.toHaveBeenCalled()
  })

  it('shows every block, in D-9 order, with Task statistics inside the activities block', async () => {
    grantedPermissions = [
      'tasks.viewAny',
      'time-entries.viewAny',
      'opportunities.viewAny',
      'quotes.viewAny',
      'leads.viewAny',
      'registries.viewAny',
      'request-management.report',
    ]
    renderScreen()

    const headings = await screen.findAllByRole('heading', { level: 2 })
    expect(headings.map((heading) => heading.textContent)).toEqual([
      'Activities to complete',
      'Time tracking',
      'Opportunities',
      'Quotes',
      'Leads',
      'Registries',
    ])
    expect(screen.queryByRole('heading', { name: 'Request management' })).not.toBeInTheDocument()
    expect(fetchDashboardTaskCountersMock).toHaveBeenCalledTimes(1)
    expect(fetchTimeEntriesOverviewMock).toHaveBeenCalledWith({ period_preset: 'day' })
    expect(fetchModuleStatsMock).toHaveBeenCalledWith('tasks')
    expect(fetchModuleStatsMock).toHaveBeenCalledWith('opportunities')
    expect(fetchModuleStatsMock).toHaveBeenCalledWith('quotes')
    expect(fetchModuleStatsMock).toHaveBeenCalledWith('leads')
    expect(fetchModuleStatsMock).toHaveBeenCalledWith('registries')
  })
})
