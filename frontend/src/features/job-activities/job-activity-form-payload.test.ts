import { describe, expect, it } from 'vitest'
import { buildCreatePayload, buildUpdatePayload } from '@/features/job-activities/job-activity-form-payload'
import type { JobActivityDetail } from '@/features/job-activities/types'

const ORIGINAL: JobActivityDetail = {
  id: 1,
  name: 'Cleaning',
  description: 'Daily',
  job_role_ids: [3],
  job_roles: [{ id: 3, name: 'Clerk' }],
  created_at: '2026-10-16T08:00:00Z',
  updated_at: '2026-10-16T08:00:00Z',
  abilities: { update: true, delete: true },
}

const VALUES = { name: 'Cleaning', description: 'Daily' }

describe('buildCreatePayload (spec 0271)', () => {
  it('trims the name and never carries job roles', () => {
    expect(buildCreatePayload({ ...VALUES, name: ' Cleaning ' })).toEqual({ name: 'Cleaning', description: 'Daily' })
  })

  it('sends a blank description as null', () => {
    expect(buildCreatePayload({ ...VALUES, description: '  ' }).description).toBeNull()
  })
})

describe('buildUpdatePayload (spec 0271)', () => {
  it('is empty when nothing changed', () => {
    expect(buildUpdatePayload(VALUES, ORIGINAL)).toEqual({})
  })

  it('carries only the changed fields', () => {
    expect(buildUpdatePayload({ ...VALUES, name: 'Filing' }, ORIGINAL)).toEqual({ name: 'Filing' })
  })

  it('maps a cleared description to null', () => {
    expect(buildUpdatePayload({ ...VALUES, description: '' }, ORIGINAL)).toEqual({ description: null })
  })
})
