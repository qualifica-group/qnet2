import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildCreateWorkOrderSchema, buildUpdateWorkOrderSchema } from '@/features/work-orders/work-order-schema'

const VALID_BASE = {
  code: 'COM-0001',
  quote_id: 4,
  title: 'Installazione impianto',
  type: 'processing' as const,
  callback_date: null,
  description: null,
  internal_notes: null,
  is_force_closed: false,
  force_close_reason: null,
  quote_line_ids: [1, 2],
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('buildCreateWorkOrderSchema — force close reason (AC-073)', () => {
  it('rejects the submit when force-closed without a reason', () => {
    const schema = buildCreateWorkOrderSchema(i18n.t.bind(i18n))
    const result = schema.safeParse({ ...VALID_BASE, is_force_closed: true, force_close_reason: null })

    expect(result.success).toBe(false)
    const issue = !result.success && result.error.issues.find((entry) => entry.path.join('.') === 'force_close_reason')
    expect(issue).toBeDefined()
  })

  it('rejects a blank (whitespace-only) reason too', () => {
    const schema = buildCreateWorkOrderSchema(i18n.t.bind(i18n))
    const result = schema.safeParse({ ...VALID_BASE, is_force_closed: true, force_close_reason: '   ' })

    expect(result.success).toBe(false)
  })

  it('accepts the submit when force-closed with a reason', () => {
    const schema = buildCreateWorkOrderSchema(i18n.t.bind(i18n))
    const result = schema.safeParse({
      ...VALID_BASE,
      is_force_closed: true,
      force_close_reason: 'Cliente insolvente',
    })

    expect(result.success).toBe(true)
  })

  it('does not require a reason when not force-closed', () => {
    const schema = buildCreateWorkOrderSchema(i18n.t.bind(i18n))
    const result = schema.safeParse({ ...VALID_BASE, is_force_closed: false, force_close_reason: null })

    expect(result.success).toBe(true)
  })

  it('rejects a create submit with no linked offer', () => {
    const schema = buildCreateWorkOrderSchema(i18n.t.bind(i18n))
    const result = schema.safeParse({ ...VALID_BASE, quote_id: null })

    expect(result.success).toBe(false)
    const issue = !result.success && result.error.issues.find((entry) => entry.path.join('.') === 'quote_id')
    expect(issue).toBeDefined()
  })

  it('rejects an empty code (D-1: the create form always prefills it)', () => {
    const schema = buildCreateWorkOrderSchema(i18n.t.bind(i18n))
    const result = schema.safeParse({ ...VALID_BASE, code: '' })

    expect(result.success).toBe(false)
  })
})

describe('buildUpdateWorkOrderSchema — force close reason (AC-073)', () => {
  it('rejects the submit when force-closed without a reason', () => {
    const schema = buildUpdateWorkOrderSchema(i18n.t.bind(i18n))
    const result = schema.safeParse({ ...VALID_BASE, is_force_closed: true, force_close_reason: null })

    expect(result.success).toBe(false)
  })

  it('does not require a linked offer (D-5: quote_id is read-only, not validated here)', () => {
    const schema = buildUpdateWorkOrderSchema(i18n.t.bind(i18n))
    const result = schema.safeParse({ ...VALID_BASE, quote_id: null })

    expect(result.success).toBe(true)
  })
})
