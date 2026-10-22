import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { AbsenceSummaryPanel } from '@/features/absences/summary/absence-summary-panel'
import { absenceWrapper } from '@/features/absences/requests/absence-request-fixtures'
import type { AbsenceSummary } from '@/features/absences/types'

const permissions = new Set<string>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => permissions.has(permission), hasRole: () => false, roles: [], isLoading: false }),
}))

const summaryMock = vi.fn()
vi.mock('@/features/absences/api', () => ({ getAbsenceSummary: () => summaryMock() }))

const SUMMARY: AbsenceSummary = {
  to_approve_count: 0,
  my_pending_count: 0,
  daily_minutes: 480,
  my_upcoming: [],
  absent_today: [],
}

const label = (key: string, options?: Record<string, unknown>) => i18n.t(key, options)

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  vi.clearAllMocks()
  permissions.clear()
  permissions.add('absence-requests.viewAny')
})

describe('AbsenceSummaryPanel (spec 0304 AC-020)', () => {
  it('lists the upcoming absences with type, mode, duration and state, and opens the detail on click', async () => {
    summaryMock.mockResolvedValue({
      ...SUMMARY,
      my_pending_count: 2,
      my_upcoming: [
        {
          request_id: 31,
          absence_type: { id: 1, name: 'Ferie', color: 'emerald' },
          status: 'approved',
          starts_on: '2026-11-02',
          ends_on: '2026-11-03',
          is_full_day: true,
          day_part: null,
          minutes: 600,
        },
        {
          request_id: 32,
          absence_type: { id: 2, name: 'Permesso', color: 'blue' },
          status: 'submitted',
          starts_on: '2026-11-10',
          ends_on: '2026-11-10',
          is_full_day: false,
          day_part: 'morning',
          minutes: 240,
        },
      ],
    })
    const onOpen = vi.fn()
    render(<AbsenceSummaryPanel onOpen={onOpen} onGoToApprove={vi.fn()} />, { wrapper: absenceWrapper() })

    const full = await screen.findByRole('button', { name: /Ferie/ })
    expect(full).toHaveTextContent('1 g 2 h')
    expect(full).toHaveTextContent(label('absences.status.approved'))
    const half = screen.getByRole('button', { name: /Permesso/ })
    expect(half).toHaveTextContent(`${label('absences.requests.form.modes.half_day')} (${label('absences.requests.form.dayParts.morning')})`)
    expect(half).toHaveTextContent(label('absences.status.submitted'))
    expect(screen.getByText(label('absences.summary.pendingCount', { count: 2 }))).toBeInTheDocument()

    fireEvent.click(half)
    expect(onOpen).toHaveBeenCalledWith(32)
  })

  it('shows the empty states and no banner when nothing is planned or to decide', async () => {
    summaryMock.mockResolvedValue(SUMMARY)
    render(<AbsenceSummaryPanel onOpen={vi.fn()} onGoToApprove={vi.fn()} />, { wrapper: absenceWrapper() })

    expect(await screen.findByText(label('absences.summary.upcomingEmpty'))).toBeInTheDocument()
    expect(screen.getByText(label('absences.summary.pendingEmpty'))).toBeInTheDocument()
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
  })

  it('shows the banner of the requests to approve and moves to the tab from its button', async () => {
    summaryMock.mockResolvedValue({ ...SUMMARY, to_approve_count: 3 })
    const onGoToApprove = vi.fn()
    render(<AbsenceSummaryPanel onOpen={vi.fn()} onGoToApprove={onGoToApprove} />, { wrapper: absenceWrapper() })

    expect(await screen.findByRole('status')).toHaveTextContent(label('absences.summary.toApproveBanner', { count: 3 }))
    fireEvent.click(screen.getByRole('button', { name: label('absences.summary.toApproveLink') }))
    expect(onGoToApprove).toHaveBeenCalled()
  })

  it('does not ask the server without the viewAny permission', () => {
    permissions.clear()
    render(<AbsenceSummaryPanel onOpen={vi.fn()} onGoToApprove={vi.fn()} />, { wrapper: absenceWrapper() })

    expect(summaryMock).not.toHaveBeenCalled()
    expect(screen.queryByRole('region')).not.toBeInTheDocument()
  })
})
