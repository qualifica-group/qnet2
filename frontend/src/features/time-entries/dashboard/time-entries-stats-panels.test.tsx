import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { TimeEntriesStatsPanels } from '@/features/time-entries/dashboard/time-entries-stats-panels'
import type { OverviewStats, PulseStats } from '@/features/time-entries/types'

/** Spec 0122 D-11/AC-034: the 4 KPI tiles and the pulse coverage/cluster panel. */

const fetchOverviewMock = vi.fn()
const fetchPulseMock = vi.fn()
vi.mock('@/features/time-entries/api', () => ({
  fetchTimeEntriesOverview: (...args: unknown[]) => fetchOverviewMock(...args),
  fetchTimeEntriesPulse: (...args: unknown[]) => fetchPulseMock(...args),
}))

const OVERVIEW: OverviewStats = {
  period: { date_from: '2026-09-07', date_to: '2026-09-13' },
  daily_target_minutes: 480,
  period_target_minutes: 2400,
  working_days: 5,
  tracked_minutes: 2100,
  tracked_days: 4,
  average_daily_focus_minutes: 525,
  anomalies: { over_target_days: 2, under_target_days: 1 },
}

const PULSE: PulseStats = {
  coverage: { percentage: 88, working_days: 5, tracked_days: 4 },
  primary_cluster: {
    task_type: { id: 1, name: 'Riunione', color: 'blue', icon: 'users' },
    minutes: 900,
    percentage: 43,
  },
  other_clusters: [
    { task_type: { id: 2, name: 'Chiamata', color: 'emerald', icon: 'phone' }, minutes: 600, percentage: 29 },
    { task_type: { id: 3, name: 'Email', color: 'amber', icon: 'mail' }, minutes: 600, percentage: 28 },
  ],
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchOverviewMock.mockReset().mockResolvedValue(OVERVIEW)
  fetchPulseMock.mockReset().mockResolvedValue(PULSE)
})

function renderPanels() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(<TimeEntriesStatsPanels params={{}} />, {
    wrapper: ({ children }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>,
  })
}

describe('TimeEntriesStatsPanels (AC-034)', () => {
  it('renders the 4 overview tiles with the q-net wording', async () => {
    renderPanels()

    expect(await screen.findByText('Period target')).toBeInTheDocument()
    expect(screen.getByText('40h 00m')).toBeInTheDocument()
    expect(screen.getByText('(8h 00m/day)')).toBeInTheDocument()
    expect(screen.getByText('required for the selected user')).toBeInTheDocument()

    expect(screen.getByText('Tracked this page')).toBeInTheDocument()
    expect(screen.getByText('35h 00m')).toBeInTheDocument()
    expect(screen.getByText('4 tracked days')).toBeInTheDocument()

    expect(screen.getByText('Average daily focus')).toBeInTheDocument()
    expect(screen.getByText('8h 45m')).toBeInTheDocument()
    expect(screen.getByText('current page average')).toBeInTheDocument()

    expect(screen.getByText('Anomalies')).toBeInTheDocument()
    expect(screen.getByText('3')).toBeInTheDocument()
    expect(screen.getByText('over target')).toBeInTheDocument()
    expect(screen.getByText('under target')).toBeInTheDocument()
  })

  it('shows the pulse coverage and the primary cluster, with the other clusters popover', async () => {
    renderPanels()

    expect(await screen.findByText('Coverage')).toBeInTheDocument()
    expect(screen.getByText('88%')).toBeInTheDocument()
    expect(screen.getByText('4/5 working days')).toBeInTheDocument()
    expect(screen.getByText('Daily coverage on the loaded range')).toBeInTheDocument()

    expect(screen.getByText('Riunione')).toBeInTheDocument()

    const otherClustersButton = screen.getByRole('button', { name: 'Other clusters (2)' })
    fireEvent.click(otherClustersButton)

    expect(await screen.findByText('Chiamata')).toBeInTheDocument()
    expect(screen.getByText('Email')).toBeInTheDocument()
  })

  it('shows "no data" on the Tracked tile when there are no tracked days', async () => {
    fetchOverviewMock.mockResolvedValue({ ...OVERVIEW, tracked_days: 0 })
    renderPanels()

    await screen.findByText('Tracked this page')
    expect(screen.getByText('No data available')).toBeInTheDocument()
    expect(screen.queryByText(/tracked days$/)).not.toBeInTheDocument()
  })

  it('renders the tile skeletons while loading', () => {
    fetchOverviewMock.mockReturnValue(new Promise(() => {}))
    fetchPulseMock.mockReturnValue(new Promise(() => {}))
    renderPanels()

    expect(screen.queryByText('Period target')).not.toBeInTheDocument()
  })
})
