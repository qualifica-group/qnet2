import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen, within } from '@testing-library/react'
import i18n from '@/i18n'
import { AbsenceInsightPanel } from '@/features/absences/requests/absence-insight-panel'
import { buildInsight } from '@/features/absences/requests/absence-request-fixtures'

const label = (key: string, options?: Record<string, unknown>) => i18n.t(key, options ?? {}) as string

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

describe('AbsenceInsightPanel', () => {
  it('shows working days and the duration in days and hours, with the balance after', () => {
    render(<AbsenceInsightPanel insight={buildInsight({ minutes: 1560 })} isPending={false} isError={false} variant="preview" />)

    expect(screen.getByText(label('absences.insight.workingDays')).nextSibling).toHaveTextContent('3')
    expect(screen.getByText(label('absences.insight.duration')).nextSibling).toHaveTextContent('3 g 2 h')
    expect(screen.getByText(label('absences.insight.balanceCurrent', { year: 2026 })).nextSibling).toHaveTextContent('10 g')
    expect(screen.getByText(label('absences.insight.balanceAfter')).nextSibling).toHaveTextContent('7 g')
  })

  it('flags a negative balance with text, not only colour', () => {
    const insight = buildInsight({ balance: { year: 2026, residual_minutes: 480, residual_after_minutes: -480 } })
    render(<AbsenceInsightPanel insight={insight} isPending={false} isError={false} variant="preview" />)

    expect(screen.getByText(`(${label('absences.insight.balanceNegative')})`)).toBeInTheDocument()
  })

  it('says a type without balance deducts nothing', () => {
    render(<AbsenceInsightPanel insight={buildInsight({ balance: null })} isPending={false} isError={false} variant="preview" />)

    expect(screen.getByText(label('absences.insight.balanceNone'))).toBeInTheDocument()
  })

  it('warns about short notice with the days the type asks', () => {
    const insight = buildInsight({ notice: { min_notice_days: 5, is_short_notice: true } })
    render(<AbsenceInsightPanel insight={insight} isPending={false} isError={false} variant="preview" />)

    expect(screen.getByText(label('absences.insight.shortNotice', { count: 5 }))).toBeInTheDocument()
  })

  it('lists the non-working days with reason and name', () => {
    const insight = buildInsight({
      non_working_days: [
        { date: '2026-11-01', reason: 'weekend', name: null },
        { date: '2026-12-08', reason: 'holiday', name: 'Immacolata' },
      ],
    })
    render(<AbsenceInsightPanel insight={insight} isPending={false} isError={false} variant="preview" />)

    const list = within(screen.getByRole('heading', { name: label('absences.insight.nonWorkingDays') }).parentElement as HTMLElement)
    expect(list.getByText(/Weekend/)).toBeInTheDocument()
    expect(list.getByText(/Festivo \(Immacolata\)/)).toBeInTheDocument()
  })

  it('warns that an own overlap will make the send fail', () => {
    const insight = buildInsight({
      overlaps: [{ request_id: 3, status: 'approved', starts_on: '2026-11-03', ends_on: '2026-11-03', absence_type_name: 'Malattia' }],
    })
    render(<AbsenceInsightPanel insight={insight} isPending={false} isError={false} variant="preview" />)

    expect(screen.getByText(label('absences.insight.overlapBlocked'))).toBeInTheDocument()
    expect(screen.getByText(/Si sovrappone a Malattia/)).toBeInTheDocument()
  })

  it('lists the absent colleagues with type and status as text next to the colour', () => {
    const insight = buildInsight({
      colleagues_absent: [
        {
          user: { id: 8, name: 'Bruno Neri' },
          request_id: 4,
          status: 'submitted',
          starts_on: '2026-11-02',
          ends_on: '2026-11-02',
          absence_type_name: 'Ferie',
          color: 'amber',
          is_full_day: false,
          day_part: 'morning',
        },
      ],
    })
    render(<AbsenceInsightPanel insight={insight} isPending={false} isError={false} variant="preview" />)

    expect(screen.getByText(/Bruno Neri: Ferie/)).toHaveTextContent('Mattina - Inviata')
  })

  it('shows the person only in the context variant and the empty colleagues state', () => {
    render(<AbsenceInsightPanel insight={buildInsight()} isPending={false} isError={false} variant="context" />)

    expect(screen.getByRole('region', { name: label('absences.insight.contextTitle') })).toBeInTheDocument()
    expect(screen.getByText('Anna Rossi')).toBeInTheDocument()
    expect(screen.getByText(label('absences.insight.colleaguesEmpty'))).toBeInTheDocument()
  })

  it('shows the loading, empty and error states compactly', () => {
    const { rerender } = render(<AbsenceInsightPanel isPending isError={false} variant="preview" />)
    expect(screen.getByRole('status')).toHaveTextContent(label('absences.insight.loading'))

    rerender(<AbsenceInsightPanel isPending={false} isError={false} variant="preview" />)
    expect(screen.getByText(label('absences.insight.previewEmpty'))).toBeInTheDocument()

    rerender(<AbsenceInsightPanel isPending={false} isError variant="preview" />)
    expect(screen.getByRole('alert')).toHaveTextContent(label('absences.insight.error'))
  })
})
