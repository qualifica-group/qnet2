import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildQuoteSystemStatusSchema } from '@/features/quote-system-statuses/quote-system-status-schema'
import { buildCreatePayload, buildUpdatePayload } from '@/features/quote-system-statuses/quote-system-status-form-payload'
import type { QuoteSystemStatusDetail } from '@/features/quote-system-statuses/types'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const valid = { name: 'Provisionally won', color: 'violet', behavior: 'validated' } as const

describe('buildQuoteSystemStatusSchema', () => {
  const schema = () => buildQuoteSystemStatusSchema(i18n.t)

  it('accepts a complete status', () => {
    expect(schema().safeParse(valid).success).toBe(true)
  })

  it('requires the name, capped at 100 characters', () => {
    expect(schema().safeParse({ ...valid, name: '  ' }).error?.issues[0].message).toBe('The name is required.')
    expect(schema().safeParse({ ...valid, name: 'x'.repeat(101) }).error?.issues[0].message).toBe(
      'The name can be at most 100 characters.',
    )
  })

  it('requires the color, like the server', () => {
    expect(schema().safeParse({ ...valid, color: '' }).error?.issues[0].message).toBe('The color is required.')
  })

  it('rejects a behavior the code does not know', () => {
    expect(schema().safeParse({ ...valid, behavior: 'archived' }).success).toBe(false)
  })
})

describe('quote system status payloads', () => {
  const original: QuoteSystemStatusDetail = {
    id: 3,
    name: 'Provisionally won',
    color: 'violet',
    behavior: 'validated',
    system_key: null,
    sort_order: 3,
    workflow_statuses_count: 2,
    created_at: '2026-11-16T09:00:00Z',
    updated_at: '2026-11-16T09:00:00Z',
  }

  it('creates with the three writable fields only', () => {
    expect(buildCreatePayload(valid)).toEqual(valid)
  })

  it('updates only what changed', () => {
    expect(buildUpdatePayload(valid, original)).toEqual({})
    expect(buildUpdatePayload({ ...valid, name: 'Won (prov.)', behavior: 'closed_won' }, original)).toEqual({
      name: 'Won (prov.)',
      behavior: 'closed_won',
    })
  })
})
