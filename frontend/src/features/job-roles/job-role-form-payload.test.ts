import { describe, expect, it } from 'vitest'
import { buildCreatePayload, buildUpdatePayload } from '@/features/job-roles/job-role-form-payload'
import type { JobRoleDetail } from '@/features/job-roles/types'

const ORIGINAL: JobRoleDetail = {
  id: 1,
  name: 'Clerk',
  description: 'Office work',
  job_activity_ids: [1, 2],
  job_activities: [
    { id: 1, name: 'Cleaning' },
    { id: 2, name: 'Filing' },
  ],
  users_count: 3,
  created_at: '2026-10-16T08:00:00Z',
  updated_at: '2026-10-16T08:00:00Z',
  abilities: { update: true, delete: true },
}

const VALUES = { name: 'Clerk', description: 'Office work', job_activity_ids: [1, 2] }

describe('buildCreatePayload (spec 0271)', () => {
  it('trims the name and carries the company ids', () => {
    expect(buildCreatePayload({ ...VALUES, name: ' Clerk ' })).toEqual({
      name: 'Clerk',
      description: 'Office work',
      job_activity_ids: [1, 2],
    })
  })

  it('sends a blank description as null', () => {
    expect(buildCreatePayload({ ...VALUES, description: '   ' }).description).toBeNull()
  })
})

describe('buildUpdatePayload (spec 0271)', () => {
  it('is empty when nothing changed, whatever the order of the ids', () => {
    expect(buildUpdatePayload({ ...VALUES, job_activity_ids: [2, 1] }, ORIGINAL)).toEqual({})
  })

  it('carries only the changed fields', () => {
    expect(buildUpdatePayload({ ...VALUES, name: 'Manager', job_activity_ids: [1] }, ORIGINAL)).toEqual({
      name: 'Manager',
      job_activity_ids: [1],
    })
  })

  it('maps a cleared description to null and an emptied set to []', () => {
    expect(buildUpdatePayload({ ...VALUES, description: '', job_activity_ids: [] }, ORIGINAL)).toEqual({
      description: null,
      job_activity_ids: [],
    })
  })
})
