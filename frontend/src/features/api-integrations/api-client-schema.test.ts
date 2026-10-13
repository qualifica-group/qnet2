import { describe, expect, it } from 'vitest'
import type { TFunction } from 'i18next'
import { buildApiClientSchema } from '@/features/api-integrations/api-client-schema'
import {
  apiClientToFormValues,
  buildCreatePayload,
  buildUpdatePayload,
  EMPTY_API_CLIENT_FORM,
  toDateTimeLocal,
} from '@/features/api-integrations/api-client-form-values'
import type { ApiClient } from '@/features/api-integrations/types'

const t = ((key: string) => key) as unknown as TFunction

const valid = { ...EMPTY_API_CLIENT_FORM, name: 'ERP' }

function issueMessages(values: unknown, initialExpiresAt: string | null = null): string[] {
  const result = buildApiClientSchema(t, initialExpiresAt).safeParse(values)
  return result.success ? [] : result.error.issues.map((issue) => issue.message)
}

describe('buildApiClientSchema', () => {
  it('accepts the minimal valid client', () => {
    expect(issueMessages(valid)).toEqual([])
  })

  it('requires a name', () => {
    expect(issueMessages({ ...valid, name: '  ' })).toContain('apiIntegrations.form.nameRequired')
  })

  it('caps name and description lengths', () => {
    expect(issueMessages({ ...valid, name: 'a'.repeat(121) })).toContain('apiIntegrations.form.nameMax')
    expect(issueMessages({ ...valid, description: 'a'.repeat(2001) })).toContain(
      'apiIntegrations.form.descriptionMax',
    )
  })

  it('bounds the rate limit to 1..1000 whole numbers and allows null', () => {
    expect(issueMessages({ ...valid, rate_limit_per_minute: 0 })).not.toEqual([])
    expect(issueMessages({ ...valid, rate_limit_per_minute: 1001 })).not.toEqual([])
    expect(issueMessages({ ...valid, rate_limit_per_minute: 1.5 })).not.toEqual([])
    expect(issueMessages({ ...valid, rate_limit_per_minute: 1000 })).toEqual([])
  })

  it('rejects a past expiry unless it is the unchanged initial value', () => {
    const past = '2020-01-01T10:00'
    expect(issueMessages({ ...valid, expires_at: past })).toContain('apiIntegrations.form.expiresAtFuture')
    expect(issueMessages({ ...valid, expires_at: past }, past)).toEqual([])
    expect(issueMessages({ ...valid, expires_at: '2999-01-01T10:00' })).toEqual([])
  })
})

const client: ApiClient = {
  id: 3,
  name: 'ERP',
  description: null,
  rate_limit_per_minute: null,
  effective_rate_limit_per_minute: 60,
  expires_at: '2020-01-01T10:00:00+00:00',
  is_active: true,
  is_expired: true,
  key_last_four: 'abcd',
  last_used_at: null,
  service_user: { id: 9, name: 'API · ERP' },
  created_by: null,
  created_at: '2020-01-01T00:00:00+00:00',
  updated_at: '2020-01-01T00:00:00+00:00',
}

describe('payload builders', () => {
  it('create maps blank description to null and omits is_active', () => {
    const payload = buildCreatePayload({ ...valid, description: '  ', expires_at: '2999-01-01T10:00' })
    expect(payload).toMatchObject({ name: 'ERP', description: null })
    expect(payload).not.toHaveProperty('scopes')
    expect(payload).not.toHaveProperty('is_active')
    expect(payload.expires_at).toBe(new Date('2999-01-01T10:00').toISOString())
  })

  it('update leaves an untouched expiry out and sends is_active', () => {
    const initial = apiClientToFormValues(client)
    const payload = buildUpdatePayload({ ...initial, is_active: false }, initial)
    expect(payload).not.toHaveProperty('expires_at')
    expect(payload.is_active).toBe(false)
  })

  it('update sends a changed expiry, including its removal', () => {
    const initial = apiClientToFormValues(client)
    expect(buildUpdatePayload({ ...initial, expires_at: null }, initial).expires_at).toBeNull()
  })

  it('round-trips an instant through the datetime-local format', () => {
    const local = toDateTimeLocal('2030-06-01T12:30:00Z')
    expect(local).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/)
    expect(new Date(local as string).toISOString()).toBe('2030-06-01T12:30:00.000Z')
    expect(toDateTimeLocal(null)).toBeNull()
  })
})
