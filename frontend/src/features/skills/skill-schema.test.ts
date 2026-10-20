import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildSkillSchema, type SkillFormValues } from '@/features/skills/skill-schema'
import { buildCreatePayload, buildUpdatePayload, skillFormValues } from '@/features/skills/skill-form-payload'
import { SKILL_LEVELS } from '@/features/skills/skill-level'
import type { Skill } from '@/features/skills/types'

const schema = buildSkillSchema(i18n.t.bind(i18n))
const VALID = { name: 'Inglese', category: null, description: null, is_active: true, job_roles: [] }

function issues(values: object): { path: string; message: string }[] {
  const result = schema.safeParse(values)
  return result.success ? [] : result.error.issues.map((issue) => ({ path: issue.path.join('.'), message: issue.message }))
}

const SKILL: Skill = {
  id: 1,
  name: 'Inglese',
  category: 'Lingue',
  description: null,
  is_active: true,
  job_roles: [{ id: 2, name: 'Operaio', min_level: 2 }, { id: 5, name: 'Capo', min_level: 4 }],
  users_count: 0,
  created_at: '2026-10-19T08:00:00Z',
  updated_at: '2026-10-19T08:00:00Z',
}

describe('skill levels', () => {
  it('are the fixed scale 1..4', () => {
    expect(SKILL_LEVELS).toEqual([1, 2, 3, 4])
  })
})

describe('skill schema (spec 0286)', () => {
  it('accepts a minimal skill', () => {
    expect(issues(VALID)).toEqual([])
  })

  it('requires a name up to 255 characters and a category up to 100', () => {
    expect(issues({ ...VALID, name: '  ' }).map((i) => i.path)).toEqual(['name'])
    expect(issues({ ...VALID, name: 'x'.repeat(256) }).map((i) => i.path)).toEqual(['name'])
    expect(issues({ ...VALID, category: 'x'.repeat(101) }).map((i) => i.path)).toEqual(['category'])
    expect(issues({ ...VALID, category: 'x'.repeat(100) })).toEqual([])
  })

  it('refuses a job role row without the job role', () => {
    const found = issues({ ...VALID, job_roles: [{ job_role_id: null, min_level: 1 }] })
    expect(found).toEqual([{ path: 'job_roles', message: i18n.t('skills.form.jobRoleRequired') }])
  })

  it('refuses the same job role twice', () => {
    const rows = [{ job_role_id: 2, min_level: 1 }, { job_role_id: 2, min_level: 3 }]
    expect(issues({ ...VALID, job_roles: rows })).toEqual([
      { path: 'job_roles', message: i18n.t('skills.form.jobRoleDuplicate') },
    ])
  })

  it('refuses a minimum level outside 1..4', () => {
    expect(issues({ ...VALID, job_roles: [{ job_role_id: 2, min_level: 5 }] }).length).toBeGreaterThan(0)
    expect(issues({ ...VALID, job_roles: [{ job_role_id: 2, min_level: 0 }] }).length).toBeGreaterThan(0)
    expect(issues({ ...VALID, job_roles: [{ job_role_id: 2, min_level: 4 }] })).toEqual([])
  })
})

describe('skill payload', () => {
  it('sends job roles as { id, min_level } and blank optional texts as null', () => {
    expect(
      buildCreatePayload({ ...VALID, category: '  ', description: '', job_roles: [{ job_role_id: 2, min_level: 3 }] }),
    ).toEqual({
      name: 'Inglese',
      category: null,
      description: null,
      is_active: true,
      job_roles: [{ id: 2, min_level: 3 }],
    })
  })

  it('sends only the changed fields on update, ignoring the order of the job roles', () => {
    const reordered: SkillFormValues['job_roles'] = [{ job_role_id: 5, min_level: 4 }, { job_role_id: 2, min_level: 2 }]
    const lowered: SkillFormValues['job_roles'] = [{ job_role_id: 5, min_level: 3 }, { job_role_id: 2, min_level: 2 }]
    const values = { ...skillFormValues(SKILL), job_roles: reordered }
    expect(buildUpdatePayload(values, SKILL)).toEqual({})
    expect(buildUpdatePayload({ ...values, job_roles: lowered }, SKILL)).toEqual({ job_roles: [{ id: 5, min_level: 3 }, { id: 2, min_level: 2 }] })
  })
})
