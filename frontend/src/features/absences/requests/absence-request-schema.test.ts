import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  EMPTY_ABSENCE_FORM,
  absenceFormValues,
  allowedAbsenceModes,
  toAbsencePayload,
  toCreatePayload,
  toPreviewPayload,
} from '@/features/absences/requests/absence-request-payload'
import { buildRequest, buildTypeOption } from '@/features/absences/requests/absence-request-fixtures'
import { buildAbsenceRequestSchema, buildRejectSchema } from '@/features/absences/requests/absence-request-schema'

const schema = buildAbsenceRequestSchema(i18n.t.bind(i18n))
const fullDay = { ...EMPTY_ABSENCE_FORM, absence_type_id: 1, starts_on: '2026-11-02', ends_on: '2026-11-04' }
const hourly = { ...fullDay, mode: 'hours' as const, requested_minutes: '02:30' }
const halfDay = { ...fullDay, mode: 'half_day' as const, day_part: 'afternoon' as const, ends_on: '' }

function issuePaths(values: unknown): string[] {
  const result = schema.safeParse(values)
  return result.success ? [] : result.error.issues.map((issue) => issue.path.join('.'))
}

describe('absence request schema', () => {
  it('accepts a valid full-day and a valid hourly request', () => {
    expect(issuePaths(fullDay)).toEqual([])
    expect(issuePaths(hourly)).toEqual([])
  })

  it('accepts a half day with a single date and no duration', () => {
    expect(issuePaths(halfDay)).toEqual([])
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

  it('sends a half day on one day with its part and no minutes', () => {
    expect(toAbsencePayload(halfDay)).toEqual({
      absence_type_id: 1,
      is_full_day: false,
      day_part: 'afternoon',
      starts_on: '2026-11-02',
      ends_on: '2026-11-02',
      note: null,
    })
  })

  it('adds the person only when somebody else is chosen', () => {
    expect(toCreatePayload(fullDay, true)).not.toHaveProperty('user_id')
    expect(toCreatePayload({ ...fullDay, user_id: 9 }, false)).toMatchObject({ user_id: 9, submit: false })
  })
})

describe('toPreviewPayload', () => {
  it('waits for the minimum fields of the mode', () => {
    expect(toPreviewPayload(EMPTY_ABSENCE_FORM, null)).toBeNull()
    expect(toPreviewPayload({ ...fullDay, ends_on: '2026-11-01' }, null)).toBeNull()
    expect(toPreviewPayload({ ...hourly, requested_minutes: '' }, null)).toBeNull()
  })

  it('carries the person and the draft being edited, never the note', () => {
    expect(toPreviewPayload({ ...halfDay, user_id: 9, note: 'x' }, 5)).toEqual({
      absence_type_id: 1,
      is_full_day: false,
      day_part: 'afternoon',
      starts_on: '2026-11-02',
      ends_on: '2026-11-02',
      user_id: 9,
      request_id: 5,
    })
  })
})

describe('allowedAbsenceModes', () => {
  it('lists only the modes the type admits, all of them without a type', () => {
    expect(allowedAbsenceModes(undefined)).toEqual(['full_day', 'half_day', 'hours'])
    expect(allowedAbsenceModes(buildTypeOption({ allows_half_day: false }))).toEqual(['full_day', 'hours'])
  })
})

describe('absenceFormValues', () => {
  it('rebuilds the mode of a draft from the request', () => {
    expect(absenceFormValues(buildRequest()).mode).toBe('full_day')
    expect(absenceFormValues(buildRequest({ is_full_day: false, day_part: 'morning' })).mode).toBe('half_day')
    expect(absenceFormValues(buildRequest({ is_full_day: false, requested_minutes: 90 }))).toMatchObject({
      mode: 'hours',
      requested_minutes: '01:30',
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
