import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildStaffCourseSchema } from '@/features/staff-courses/staff-course-schema'
import {
  buildCreatePayload,
  buildUpdatePayload,
  staffCourseFormValues,
} from '@/features/staff-courses/staff-course-form-payload'
import type { StaffCourse } from '@/features/staff-courses/types'

const schema = buildStaffCourseSchema(i18n.t.bind(i18n))
const VALID = {
  name: 'Sicurezza',
  description: null,
  duration_hours: '',
  validity_months: null,
  is_active: true,
  job_role_ids: [],
}

function fieldsWithErrors(values: object): string[] {
  const result = schema.safeParse(values)
  return result.success ? [] : result.error.issues.map((issue) => String(issue.path[0]))
}

const COURSE: StaffCourse = {
  id: 1,
  name: 'Sicurezza',
  description: 'Base',
  duration_minutes: 450,
  validity_months: 60,
  is_active: true,
  job_roles: [{ id: 2, name: 'Operaio' }, { id: 5, name: 'Capo' }],
  trainings_count: 0,
  created_at: '2026-10-19T08:00:00Z',
  updated_at: '2026-10-19T08:00:00Z',
}

describe('staff course schema (spec 0284)', () => {
  it('accepts a minimal course', () => {
    expect(fieldsWithErrors(VALID)).toEqual([])
  })

  it('requires a name of at most 255 characters', () => {
    expect(fieldsWithErrors({ ...VALID, name: '   ' })).toEqual(['name'])
    expect(fieldsWithErrors({ ...VALID, name: 'x'.repeat(256) })).toEqual(['name'])
    expect(fieldsWithErrors({ ...VALID, name: 'x'.repeat(255) })).toEqual([])
  })

  it('accepts decimal hours with dot or comma and refuses text, zero and negatives', () => {
    for (const ok of ['', '8', '7.5', '7,5']) expect(fieldsWithErrors({ ...VALID, duration_hours: ok })).toEqual([])
    for (const bad of ['abc', '0', '-2', '0.001']) expect(fieldsWithErrors({ ...VALID, duration_hours: bad })).toEqual(['duration_hours'])
  })

  it('bounds the validity to 1..240 whole months, empty meaning it never expires', () => {
    for (const ok of [null, 1, 240]) expect(fieldsWithErrors({ ...VALID, validity_months: ok })).toEqual([])
    for (const bad of [0, 241, 1.5]) expect(fieldsWithErrors({ ...VALID, validity_months: bad })).toEqual(['validity_months'])
  })
})

describe('staff course payload', () => {
  it('turns hours into whole minutes and a blank description into null', () => {
    expect(buildCreatePayload({ ...VALID, duration_hours: '7,5', description: '  ' })).toEqual({
      name: 'Sicurezza',
      description: null,
      duration_minutes: 450,
      validity_months: null,
      is_active: true,
      job_role_ids: [],
    })
  })

  it('sends no duration when the field is empty', () => {
    expect(buildCreatePayload(VALID).duration_minutes).toBeNull()
  })

  it('shows saved minutes as decimal hours', () => {
    expect(staffCourseFormValues(COURSE)).toMatchObject({ duration_hours: '7.5', job_role_ids: [2, 5] })
  })

  it('sends only the changed fields on update, ignoring the order of the job roles', () => {
    const values = { ...staffCourseFormValues(COURSE), job_role_ids: [5, 2] }
    expect(buildUpdatePayload(values, COURSE)).toEqual({})
    expect(buildUpdatePayload({ ...values, validity_months: 12, job_role_ids: [5] }, COURSE)).toEqual({
      validity_months: 12,
      job_role_ids: [5],
    })
  })
})
