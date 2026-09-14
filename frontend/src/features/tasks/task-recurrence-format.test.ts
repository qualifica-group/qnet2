import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { formatTaskRecurrenceRule } from '@/features/tasks/task-recurrence-format'
import { taskRecurrenceDetail } from '@/features/tasks/task-fixtures'

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

/** Spec 0120 AC-035: the exact sentences the acceptance criterion names, plus the two omitted branches. */
describe('formatTaskRecurrenceRule (spec 0120 AC-035)', () => {
  it('renders "Ogni giorno" for a daily rule with interval 1', () => {
    const rule = taskRecurrenceDetail({
      frequency: 'daily',
      interval: 1,
      weekdays: null,
      ends: 'never',
      ends_on: null,
    })

    expect(formatTaskRecurrenceRule(rule, i18n.t, 'it')).toBe('Ogni giorno')
  })

  it('renders "Ogni 2 settimane il lunedì e il mercoledì" for a weekly rule', () => {
    const rule = taskRecurrenceDetail({
      frequency: 'weekly',
      interval: 2,
      weekdays: [1, 3],
      ends: 'never',
      ends_on: null,
    })

    expect(formatTaskRecurrenceRule(rule, i18n.t, 'it')).toBe('Ogni 2 settimane il lunedì e il mercoledì')
  })

  it('renders "Ogni mese il giorno 31" for a monthly rule with interval 1', () => {
    const rule = taskRecurrenceDetail({
      frequency: 'monthly',
      interval: 1,
      month_day: 31,
      weekdays: null,
      ends: 'never',
      ends_on: null,
    })

    expect(formatTaskRecurrenceRule(rule, i18n.t, 'it')).toBe('Ogni mese il giorno 31')
  })

  it('sorts the weekday list ISO-ascending regardless of storage order', () => {
    const rule = taskRecurrenceDetail({
      frequency: 'weekly',
      interval: 1,
      weekdays: [5, 1],
      ends: 'never',
      ends_on: null,
    })

    expect(formatTaskRecurrenceRule(rule, i18n.t, 'it')).toBe('Ogni settimana il lunedì e il venerdì')
  })

  it('appends the end date for `ends: on_date`', () => {
    const rule = taskRecurrenceDetail({
      frequency: 'daily',
      interval: 1,
      weekdays: null,
      ends: 'on_date',
      ends_on: '2027-03-31',
    })

    expect(formatTaskRecurrenceRule(rule, i18n.t, 'it')).toBe('Ogni giorno, fino al 31/03/2027')
  })

  it('appends the occurrence count for `ends: after_count`, singular included', () => {
    const rule = taskRecurrenceDetail({
      frequency: 'daily',
      interval: 1,
      weekdays: null,
      ends: 'after_count',
      occurrence_count: 1,
    })

    expect(formatTaskRecurrenceRule(rule, i18n.t, 'it')).toBe('Ogni giorno, per 1 occorrenza')
  })

  it('translates the whole sentence in en', async () => {
    await i18n.changeLanguage('en')
    const rule = taskRecurrenceDetail({
      frequency: 'weekly',
      interval: 2,
      weekdays: [1, 3],
      ends: 'never',
      ends_on: null,
    })

    expect(formatTaskRecurrenceRule(rule, i18n.t, 'en')).toBe('Every 2 weeks on Monday and Wednesday')
    await i18n.changeLanguage('it')
  })
})
