import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { TaskRecurrenceSummary } from '@/features/tasks/task-recurrence-summary'
import { taskRecurrenceDetail } from '@/features/tasks/task-fixtures'

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

describe('TaskRecurrenceSummary', () => {
  it('shows the off placeholder with its hint when there is no rule', () => {
    render(<TaskRecurrenceSummary rule={null} />)

    expect(screen.getByText('Non ricorrente')).toBeInTheDocument()
    expect(screen.getByText(i18n.t('tasks.detail.recurrenceCard.offHint'))).toBeInTheDocument()
  })

  it('names the frequency and the rule sentence, then the end date and the workdays fact', () => {
    render(<TaskRecurrenceSummary rule={taskRecurrenceDetail({ workdays_only: true })} />)

    expect(screen.getByText('Settimanale')).toBeInTheDocument()
    expect(
      screen.getByText('Ogni 2 settimane il lunedì e il mercoledì, solo nei giorni lavorativi, fino al 31/03/2027'),
    ).toBeInTheDocument()
    expect(screen.getByText('Fino al 31/03/2027')).toBeInTheDocument()
    expect(screen.getByText('Solo giorni lavorativi')).toBeInTheDocument()
  })

  it('reads an open-ended or counted series in the end fact', () => {
    const { rerender } = render(<TaskRecurrenceSummary rule={taskRecurrenceDetail({ ends: 'never', ends_on: null })} />)
    expect(screen.getByText('Nessuna data di fine')).toBeInTheDocument()

    rerender(
      <TaskRecurrenceSummary rule={taskRecurrenceDetail({ ends: 'after_count', ends_on: null, occurrence_count: 12 })} />,
    )
    expect(screen.getByText('12 occorrenze')).toBeInTheDocument()
  })
})
