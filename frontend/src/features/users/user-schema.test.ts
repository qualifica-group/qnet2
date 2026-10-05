import { describe, expect, it } from 'vitest'
import type { TFunction } from 'i18next'
import { buildCustomFieldsSchema } from '@/features/custom-fields/build-custom-fields-schema'
import { buildCreateUserSchema } from '@/features/users/user-schema'

/** Spec 0177 AC-016: on create the password pair is optional; when typed it must be valid. */

import type { ResourcePermissions } from '@/features/authorization/types'

const PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

const t = ((key: string) => key) as unknown as TFunction

const employment = {
  is_manager: false,
  job_description: '',
  reports_to_ids: [],
  relationship_type: null,
  company_id: null,
  primary_operational_site_id: null,
  remote_operational_site_ids: [],
  is_assignable: true,
  covers_all_product_categories: false,
  product_lines: [],
  qualification_type: null,
  hired_at: '',
  terminated_at: '',
  standard_daily_minutes: null,
  break_daily_minutes: null,
}

function values(password: string, confirmation: string) {
  return {
    email: 'ada@example.com',
    locale: 'it' as const,
    is_active: true,
    roles: [],
    password,
    password_confirmation: confirmation,
    employment,
    custom_fields: {},
  }
}

describe('buildCreateUserSchema password', () => {
  const schema = buildCreateUserSchema(t, buildCustomFieldsSchema([], PERMISSIONS, t))

  it('accepts empty password and confirmation', () => {
    expect(schema.safeParse(values('', '')).success).toBe(true)
  })

  it('rejects a password shorter than 8 characters', () => {
    const result = schema.safeParse(values('short', 'short'))

    expect(result.success).toBe(false)
    expect(result.error?.issues[0]?.path).toEqual(['password'])
  })

  it('rejects a confirmation that differs', () => {
    const result = schema.safeParse(values('longenough1', 'different1'))

    expect(result.success).toBe(false)
    expect(result.error?.issues[0]?.path).toEqual(['password_confirmation'])
  })

  it('accepts a valid matching pair', () => {
    expect(schema.safeParse(values('longenough1', 'longenough1')).success).toBe(true)
  })
})
