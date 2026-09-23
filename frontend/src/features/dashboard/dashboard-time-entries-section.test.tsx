import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { DashboardTimeEntriesSection } from '@/features/dashboard/dashboard-time-entries-section'
import type { OverviewStats, PulseStats } from '@/features/time-entries/types'

/** Spec 0151 AC-010: default preset "Giorno", preset change re-queries `period_preset`. */

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
  anomalies: { over_target_days: 0, under_target_days: 0 },
}
const PULSE: PulseStats = {
  coverage: { percentage: 88, working_days: 5, tracked_days: 4 },
  primary_cluster: null,
  other_clusters: [],
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchOverviewMock.mockReset().mockResolvedValue(OVERVIEW)
  fetchPulseMock.mockReset().mockResolvedValue(PULSE)
})

function renderSection() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <DashboardTimeEntriesSection />
    </QueryClientProvider>,
  )
}

describe('DashboardTimeEntriesSection (AC-010)', () => {
  it('queries the day preset by default', async () => {
    renderSection()

    await waitFor(() => expect(fetchOverviewMock).toHaveBeenCalledWith({ period_preset: 'day' }))
    expect(fetchPulseMock).toHaveBeenCalledWith({ period_preset: 'day' })
    expect(screen.getByText('Time tracking')).toBeInTheDocument()
  })

  it('re-queries with the selected preset when it changes', async () => {
    renderSection()
    await waitFor(() => expect(fetchOverviewMock).toHaveBeenCalledWith({ period_preset: 'day' }))

    fireEvent.click(screen.getByRole('button', { name: 'Weekly' }))

    await waitFor(() => expect(fetchOverviewMock).toHaveBeenCalledWith({ period_preset: 'week' }))
    expect(fetchPulseMock).toHaveBeenCalledWith({ period_preset: 'week' })
  })
})
