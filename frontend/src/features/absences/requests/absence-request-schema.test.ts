import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { EMPTY_ABSENCE_FORM, toAbsencePayload } from '@/features/absences/requests/absence-request-payload'
import { buildAbsenceRequestSchema, buildRejectSchema } from '@/features/absences/requests/absence-request-schema'

const schema = buildAbsenceRequestSchema(i18n.t.bind(i18n))
const fullDay = { ...EMPTY_ABSENCE_FORM, absence_type_id: 1, starts_on: '2026-11-02', ends_on: '2026-11-04' }
const hourly = { ...fullDay, is_full_day: false, requested_minutes: '02:30' }

function issuePaths(values: unknown): string[] {
  const result = schema.safeParse(values)
  return result.success ? [] : result.error.issues.map((issue) => issue.path.join('.'))
}

describe('absence request schema', () => {
  it('accepts a valid full-day and a valid hourly request', () => {
    expect(issuePaths(fullDay)).toEqual([])
    expect(issuePaths(hourly)).toEqual([])
  })

  it('requires type and dates', () => {
    expect(issuePaths(EMPTY_ABSENCE_FORM)).toEqual(expect.arrayContaining(['absence_type_id', 'starts_on', 'ends_on']))
  })

  it('rejects an end before the start on a full-day request', () => {
    expect(issuePaths({ ...fullDay, ends_on: '2026-11-01' })).toEqual(['ends_on'])
  })

  it('requires a positive duration on an hourly request and ignores ends_on', () => {
    expect(issuePaths({ ...hourly, ends_on: '', requested_minutes: '' })).toEqual(['requested_minutes'])
    expect(issuePaths({ ...hourly, requested_minutes: '00:00' })).toEqual(['requested_minutes'])
  })
})

describe('toAbsencePayload', () => {
  it('sends a full-day request with its own dates', () => {
    expect(toAbsencePayload({ ...fullDay, note: ' ferie ' })).toEqual({
      absence_type_id: 1,
      is_full_day: true,
      starts_on: '2026-11-02',
      ends_on: '2026-11-04',
      note: 'ferie',
    })
  })

  it('sends an hourly request on one day with the minutes indicated', () => {
    expect(toAbsencePayload(hourly)).toEqual({
      absence_type_id: 1,
      is_full_day: false,
      starts_on: '2026-11-02',
      ends_on: '2026-11-02',
      requested_minutes: 150,
      note: null,
    })
  })
})

describe('reject schema', () => {
  it('requires a non-blank reason', () => {
    const rejectSchema = buildRejectSchema(i18n.t.bind(i18n))
    expect(rejectSchema.safeParse({ rejection_reason: '   ' }).success).toBe(false)
    expect(rejectSchema.safeParse({ rejection_reason: 'Periodo scoperto' }).success).toBe(true)
  })
})
