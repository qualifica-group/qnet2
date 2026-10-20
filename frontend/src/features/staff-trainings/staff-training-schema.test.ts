import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildStaffTrainingSchema } from '@/features/staff-trainings/staff-training-schema'
import { toStaffTrainingPayload } from '@/features/staff-trainings/staff-training-payload'
import { dayOf } from '@/features/absences/calendar/calendar-month'

const schema = buildStaffTrainingSchema(i18n.t.bind(i18n))
const VALID = { user_id: 7, staff_course_id: 3, completed_on: '2026-03-10', outcome: 'passed' as const, notes: '' }

function messages(values: object): Record<string, string> {
  const result = schema.safeParse(values)
  if (result.success) return {}
  return Object.fromEntries(result.error.issues.map((issue) => [String(issue.path[0]), issue.message]))
}

describe('staff training schema (spec 0284)', () => {
  it('accepts a complete training', () => {
    expect(schema.safeParse(VALID).success).toBe(true)
  })

  it('requires person, course and completion date', () => {
    const errors = messages({ ...VALID, user_id: null, staff_course_id: null, completed_on: '' })
    expect(Object.keys(errors).sort()).toEqual(['completed_on', 'staff_course_id', 'user_id'])
  })

  it('refuses a future completion day and accepts today', () => {
    expect(messages({ ...VALID, completed_on: '2999-01-01' })).toHaveProperty('completed_on')
    expect(messages({ ...VALID, completed_on: dayOf(new Date()) })).toEqual({})
  })

  it('refuses an outcome outside passed/failed and notes over 2000 characters', () => {
    expect(schema.safeParse({ ...VALID, outcome: 'maybe' }).success).toBe(false)
    expect(messages({ ...VALID, notes: 'x'.repeat(2001) })).toHaveProperty('notes')
    expect(messages({ ...VALID, notes: 'x'.repeat(2000) })).toEqual({})
  })
})

describe('toStaffTrainingPayload', () => {
  it('sends a blank note as null and trims a typed one', () => {
    expect(toStaffTrainingPayload({ ...VALID, notes: '   ' })?.notes).toBeNull()
    expect(toStaffTrainingPayload({ ...VALID, notes: ' Ok ' })?.notes).toBe('Ok')
  })

  it('returns null when a required id is missing', () => {
    expect(toStaffTrainingPayload({ ...VALID, user_id: null })).toBeNull()
  })
})
