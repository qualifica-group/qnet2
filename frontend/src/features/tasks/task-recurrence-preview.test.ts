import { describe, expect, it } from 'vitest'
import { emptyRecurrenceDefaults } from '@/features/tasks/task-recurrence-defaults'
import { recurrencePreviewRule } from '@/features/tasks/task-recurrence-preview'
import type { TaskFormValues } from '@/features/tasks/task-schema'

type RecurrenceValues = TaskFormValues['recurrence']

function draft(overrides: Partial<RecurrenceValues>): RecurrenceValues {
  return { ...emptyRecurrenceDefaults(), enabled: true, frequency: 'daily', ends: 'never', ...overrides }
}

describe('recurrencePreviewRule', () => {
  it('returns nothing while the rule is off', () => {
    expect(recurrencePreviewRule({ ...draft({}), enabled: false })).toBeNull()
  })

  it('returns the rule without an id once every field it needs is set', () => {
    expect(recurrencePreviewRule(draft({ interval: 2 }))).toMatchObject({ frequency: 'daily', interval: 2, ends: 'never' })
    expect(recurrencePreviewRule(draft({}))).not.toHaveProperty('id')
  })

  it.each<[string, Partial<RecurrenceValues>]>([
    ['a blank interval', { interval: null }],
    ['a weekly rule without days', { frequency: 'weekly' }],
    ['a fixed monthly rule without its day', { frequency: 'monthly', month_mode: 'fixed' }],
    ['an ordinal monthly rule without its weekday', { frequency: 'monthly', month_mode: 'ordinal', ordinal: 2 }],
    ['a yearly rule without its month', { frequency: 'yearly', month_mode: 'fixed', month_day: 3 }],
    ['an end date not picked yet', { ends: 'on_date' }],
    ['an occurrence count not typed yet', { ends: 'after_count' }],
  ])('returns nothing for %s', (_case, overrides) => {
    expect(recurrencePreviewRule(draft(overrides))).toBeNull()
  })

  it('accepts complete weekly, ordinal yearly and bounded rules', () => {
    expect(recurrencePreviewRule(draft({ frequency: 'weekly', weekdays: [1, 3] }))).not.toBeNull()
    expect(
      recurrencePreviewRule(
        draft({ frequency: 'yearly', month_mode: 'ordinal', ordinal: 2, ordinal_weekday: 2, year_month: 3 }),
      ),
    ).not.toBeNull()
    expect(recurrencePreviewRule(draft({ ends: 'after_count', occurrence_count: 5 }))).not.toBeNull()
    expect(recurrencePreviewRule(draft({ ends: 'on_date', ends_on: '2027-03-31' }))).not.toBeNull()
  })
})
