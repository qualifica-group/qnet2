import { describe, expect, it } from 'vitest'
import type { TFunction } from 'i18next'
import { buildCustomFieldsSchema } from '@/features/custom-fields/build-custom-fields-schema'
import { buildCreateUserSchema } from '@/features/users/user-schema'

/** REQUIREMENT CHANGED (spec 0355 AC-011): the create schema has no password, the system generates it. */

import type { ResourcePermissions } from '@/features/authorization/types'

const PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

const t = ((key: string) => key) as unknown as TFunction

const employment = {
  job_role_ids: [],
  business_function_ids: [],
  managed_business_function_ids: [],
  reports_to_ids: [],
  primary_operational_site_id: null,
  remote_operational_site_ids: [],
  is_assignable: true,
  covers_all_product_categories: false,
  product_lines: [],
  hired_at: '',
  terminated_at: '',
  standard_daily_minutes: null,
  break_daily_minutes: null,
  iban: '',
  iban_holder: '',
}

function values() {
  return {
    email: 'ada@example.com',
    locale: 'it' as const,
    is_active: true,
    roles: [],
    employment,
    custom_fields: {},
  }
}

describe('buildCreateUserSchema without password', () => {
  const schema = buildCreateUserSchema(t, buildCustomFieldsSchema([], PERMISSIONS, t))

  it('accepts a value set with no password fields', () => {
    expect(schema.safeParse(values()).success).toBe(true)
  })

  it('drops password fields if sent', () => {
    const result = schema.safeParse({ ...values(), password: 'longenough1', password_confirmation: 'other' })

    expect(result.success).toBe(true)
    expect(result.data).not.toHaveProperty('password')
  })
})
