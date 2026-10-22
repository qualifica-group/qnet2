import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { DashboardAbsencesSection } from '@/features/dashboard/dashboard-absences-section'
import type { AbsenceSummary } from '@/features/absences/types'

/** Spec 0304 AC-025: to approve with link, who is out today, my upcoming absences. */

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))
const getAbsenceSummary = vi.fn<() => Promise<AbsenceSummary>>()
vi.mock('@/features/absences/api', () => ({ getAbsenceSummary: () => getAbsenceSummary() }))

const SUMMARY: AbsenceSummary = {
  to_approve_count: 3,
  my_pending_count: 1,
  daily_minutes: 480,
  my_upcoming: [
    {
      request_id: 42,
      absence_type: { id: 1, name: 'Ferie', color: 'blue' },
      status: 'approved',
      starts_on: '2026-12-07',
      ends_on: '2026-12-09',
      is_full_day: true,
      day_part: null,
      minutes: 1440,
    },
  ],
  absent_today: [
    {
      user: { id: 5, name: 'Anna Rossi' },
      request_id: 9,
      absence_type_name: 'Permesso',
      color: 'amber',
      is_full_day: false,
      day_part: 'morning',
      ends_on: '2026-12-01',
    },
  ],
}

function renderSection() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <DashboardAbsencesSection />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('it')
})
beforeEach(() => {
  getAbsenceSummary.mockReset()
})

describe('DashboardAbsencesSection', () => {
  it('shows the count to approve with a link, who is out today and my upcoming absences', async () => {
    getAbsenceSummary.mockResolvedValue(SUMMARY)
    renderSection()

    expect(await screen.findByText('3')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: i18n.t('dashboard.absencesSection.toApproveLink') })).toHaveAttribute('href', '/absences')

    const today = screen.getByRole('heading', { name: i18n.t('dashboard.absencesSection.absentToday') }).parentElement as HTMLElement
    expect(within(today).getByText('Anna Rossi')).toBeInTheDocument()
    expect(within(today).getByText('Permesso')).toBeInTheDocument()
    expect(within(today).getByText('Mattina')).toBeInTheDocument()

    expect(screen.getByText('Ferie')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /07\/12\/2026/ })).toHaveAttribute('href', '/absences?request=42')
  })

  it('shows compact empty states', async () => {
    getAbsenceSummary.mockResolvedValue({ ...SUMMARY, to_approve_count: 0, my_upcoming: [], absent_today: [] })
    renderSection()

    expect(await screen.findByText(i18n.t('dashboard.absencesSection.toApproveEmpty'))).toBeInTheDocument()
    expect(screen.getByText(i18n.t('dashboard.absencesSection.absentTodayEmpty'))).toBeInTheDocument()
    expect(screen.getByText(i18n.t('dashboard.absencesSection.myUpcomingEmpty'))).toBeInTheDocument()
    expect(screen.queryByRole('link')).not.toBeInTheDocument()
  })

  it('shows an alert when the summary cannot be loaded', async () => {
    getAbsenceSummary.mockImplementation(() => Promise.reject(new Error('boom')))
    renderSection()

    expect(await screen.findByRole('alert')).toHaveTextContent(i18n.t('dashboard.absencesSection.error'))
  })
})
