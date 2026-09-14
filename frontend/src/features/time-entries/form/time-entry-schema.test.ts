import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildTimeEntrySchema, type TimeEntryFormValues } from '@/features/time-entries/form/time-entry-schema'

function baseValues(overrides: Partial<TimeEntryFormValues> = {}): TimeEntryFormValues {
  return {
    title: 'Rientro cliente',
    date: '2026-09-14',
    task_type_id: 1,
    start_time: null,
    end_time: null,
    minutes: 60,
    notes: null,
    registry_id: null,
    opportunity_id: null,
    work_order_id: null,
    task_id: null,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

describe('buildTimeEntrySchema — AC-030', () => {
  const schema = () => buildTimeEntrySchema(i18n.t)

  it('accepts a minimal valid create payload', () => {
    expect(schema().safeParse(baseValues()).success).toBe(true)
  })

  it('requires a title when no task is linked', () => {
    const result = schema().safeParse(baseValues({ title: '' }))
    expect(result.success).toBe(false)
    expect(result.success ? [] : result.error.issues.map((issue) => issue.path[0])).toContain('title')
  })

  it('does not require a title once a task is linked (D-5)', () => {
    const result = schema().safeParse(baseValues({ title: '', task_id: 40 }))
    expect(result.success).toBe(true)
  })

  it('requires the date', () => {
    const result = schema().safeParse(baseValues({ date: '' }))
    expect(result.success).toBe(false)
  })

  it('requires the type', () => {
    const result = schema().safeParse(baseValues({ task_type_id: null }))
    expect(result.success).toBe(false)
  })

  it('rejects missing minutes', () => {
    const result = schema().safeParse(baseValues({ minutes: null }))
    expect(result.success).toBe(false)
  })

  it.each([0, 1441])('rejects minutes out of the 1..1440 range (%i)', (minutes) => {
    const result = schema().safeParse(baseValues({ minutes }))
    expect(result.success).toBe(false)
  })

  it('accepts the 1..1440 boundaries', () => {
    expect(schema().safeParse(baseValues({ minutes: 1 })).success).toBe(true)
    expect(schema().safeParse(baseValues({ minutes: 1440 })).success).toBe(true)
  })

  it('rejects an end time at or before the start time (D-6)', () => {
    const atStart = schema().safeParse(baseValues({ start_time: '09:00', end_time: '09:00' }))
    const beforeStart = schema().safeParse(baseValues({ start_time: '09:00', end_time: '08:30' }))
    expect(atStart.success).toBe(false)
    expect(beforeStart.success).toBe(false)
  })

  it('accepts an end time after the start time', () => {
    const result = schema().safeParse(baseValues({ start_time: '09:00', end_time: '10:30' }))
    expect(result.success).toBe(true)
  })
})
