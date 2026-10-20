import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { dayOf } from '@/features/absences/calendar/calendar-month'
import { toUserSkillPayload } from '@/features/user-skills/user-skill-payload'
import { buildUserSkillSchema } from '@/features/user-skills/user-skill-schema'

const schema = buildUserSkillSchema(i18n.t.bind(i18n))
const VALID = { user_id: 7, skill_id: 3, level: 2, assessed_on: '2026-03-10', notes: '' }

function paths(values: object): string[] {
  const result = schema.safeParse(values)
  return result.success ? [] : result.error.issues.map((issue) => issue.path.join('.')).sort()
}

describe('user skill schema (spec 0286)', () => {
  it('accepts a complete person skill', () => {
    expect(paths(VALID)).toEqual([])
  })

  it('requires person, skill, level and date', () => {
    expect(paths({ ...VALID, user_id: null, skill_id: null, level: null, assessed_on: '' })).toEqual([
      'assessed_on',
      'level',
      'skill_id',
      'user_id',
    ])
  })

  it('accepts only levels 1 to 4', () => {
    for (const level of [1, 2, 3, 4]) expect(paths({ ...VALID, level })).toEqual([])
    for (const level of [0, 5, 2.5]) expect(paths({ ...VALID, level })).toEqual(['level'])
  })

  it('refuses a future assessment day and accepts today', () => {
    expect(paths({ ...VALID, assessed_on: '2999-01-01' })).toEqual(['assessed_on'])
    expect(paths({ ...VALID, assessed_on: dayOf(new Date()) })).toEqual([])
  })

  it('caps the notes at 2000 characters', () => {
    expect(paths({ ...VALID, notes: 'x'.repeat(2001) })).toEqual(['notes'])
    expect(paths({ ...VALID, notes: 'x'.repeat(2000) })).toEqual([])
  })
})

describe('toUserSkillPayload', () => {
  it('sends a blank note as null and trims a typed one', () => {
    expect(toUserSkillPayload({ ...VALID, notes: '  ' })?.notes).toBeNull()
    expect(toUserSkillPayload({ ...VALID, notes: ' Ok ' })?.notes).toBe('Ok')
  })

  it('returns null when a required value is missing', () => {
    expect(toUserSkillPayload({ ...VALID, level: null })).toBeNull()
    expect(toUserSkillPayload({ ...VALID, skill_id: null })).toBeNull()
  })
})
