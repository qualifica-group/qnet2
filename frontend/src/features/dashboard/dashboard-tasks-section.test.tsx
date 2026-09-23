import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { DashboardTasksSection } from '@/features/dashboard/dashboard-tasks-section'
import type { DashboardTaskCounters } from '@/features/dashboard/types'
import type { ModuleStats } from '@/features/stats/types'

/** Spec 0151 AC-009: the 5 "Attività da completare" cards. */

const fetchDashboardTaskCountersMock = vi.fn<() => Promise<DashboardTaskCounters>>()
vi.mock('@/features/dashboard/api', () => ({
  fetchDashboardTaskCounters: () => fetchDashboardTaskCountersMock(),
}))

const fetchModuleStatsMock = vi.fn<(domain: string) => Promise<ModuleStats>>()
vi.mock('@/features/stats/api', () => ({
  moduleStatsQueryKey: (domain: string) => ['stats', domain],
  fetchModuleStats: (domain: string) => fetchModuleStatsMock(domain),
}))

const COUNTERS: DashboardTaskCounters = {
  not_completed: { count: 42, total_minutes: 600 },
  assigned_to_me: { count: 10, total_minutes: 90 },
  assigned_by_me: { count: 5, total_minutes: 45, to_validate: { count: 2, total_minutes: 30 } },
  created_by_me: { count: 3, total_minutes: 0 },
  observed_by_me: { count: 0, total_minutes: 0 },
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchDashboardTaskCountersMock.mockReset().mockResolvedValue(COUNTERS)
  fetchModuleStatsMock.mockReset().mockResolvedValue({ widgets: [] })
})

function renderSection() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <DashboardTasksSection />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

describe('DashboardTasksSection (AC-009)', () => {
  it('shows the Task statistics inside the same block as the cards', async () => {
    renderSection()

    const section = await screen.findByRole('region', { name: 'Activities to complete' })
    expect(section).toContainElement(screen.getByRole('region', { name: i18n.t('statsPanel.regionLabel') }))
    expect(fetchModuleStatsMock).toHaveBeenCalledWith('tasks')
  })

  it('renders the skeleton tiles while loading', () => {
    fetchDashboardTaskCountersMock.mockReturnValue(new Promise(() => {}))
    renderSection()

    expect(screen.queryByText('All')).not.toBeInTheDocument()
    expect(screen.getByText('Activities to complete')).toBeInTheDocument()
  })

  it('renders the 5 cards with their label, count, minutes badge and link', async () => {
    renderSection()

    expect(await screen.findByText('All')).toBeInTheDocument()
    expect(screen.getByText('42')).toBeInTheDocument()
    expect(screen.getByText('10h 00m')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'All' })).toHaveAttribute('href', '/tasks?status=open')
    expect(screen.getByRole('link', { name: 'Assigned to me' })).toHaveAttribute(
      'href',
      '/tasks?status=open&assignment=assigned_to_me',
    )
    expect(screen.getByRole('link', { name: 'Assigned by me' })).toHaveAttribute(
      'href',
      '/tasks?status=open&assignment=assigned_by_me',
    )
    expect(screen.getByRole('link', { name: 'Created by me' })).toHaveAttribute(
      'href',
      '/tasks?status=open&assignment=created_by_me',
    )
    expect(screen.getByRole('link', { name: 'Observed by me' })).toHaveAttribute(
      'href',
      '/tasks?status=open&assignment=observed_by_me',
    )
  })

  it('shows the "to validate" chip only on assigned_by_me, and only when its count is > 0', async () => {
    renderSection()
    await screen.findByText('All')

    const chip = screen.getByRole('link', { name: '2' })
    expect(chip).toHaveAttribute('href', '/tasks?status=in_validation&assignment=assigned_by_me')
  })

  it('hides the "to validate" chip when its count is 0', async () => {
    fetchDashboardTaskCountersMock.mockResolvedValue({
      ...COUNTERS,
      assigned_by_me: { count: 5, total_minutes: 45, to_validate: { count: 0, total_minutes: 0 } },
    })
    renderSection()
    await screen.findByText('All')

    expect(screen.queryByRole('link', { name: '0' })).not.toBeInTheDocument()
  })

  it('renders an error state with a retry button when the request fails', async () => {
    fetchDashboardTaskCountersMock.mockRejectedValue(new Error('network error'))
    renderSection()

    expect(await screen.findByRole('alert')).toHaveTextContent('The dashboard counters are not available right now.')
    expect(screen.getByRole('button', { name: 'Retry' })).toBeInTheDocument()
  })
})
