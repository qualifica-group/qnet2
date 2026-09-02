import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildCreateWorkOrderSchema, buildUpdateWorkOrderSchema } from '@/features/work-orders/work-order-schema'
import type { ApplicableAttributeSummary } from '@/features/work-orders/types'

const VALID_BASE = {
  code: 'COM-0001',
  quote_id: 4,
  title: 'Installazione impianto',
  type: 'processing' as const,
  start_date: '2026-03-01',
  supervisor_ids: [21],
  participant_slots: [31, null],
  callback_date: null,
  description: null,
  internal_notes: null,
  is_force_closed: false,
  force_close_reason: null,
  quote_line_ids: [1, 2],
  attribute_values: {},
}

function requiredTextAttribute(): ApplicableAttributeSummary {
  return {
    id: 1,
    code: 'site_access',
    name: 'Site access',
    type: 'text',
    description: null,
    help_text: null,
    placeholder: null,
    icon: null,
    config: null,
    relation_target: null,
    is_required: true,
    sort_order: 0,
    options: [],
  }
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

/** Spec 0098: the dynamic `attribute_values` shape, mirroring `quote-schema.test.ts`. */
describe('buildCreateWorkOrderSchema / buildUpdateWorkOrderSchema — attribute_values (spec 0098)', () => {
  it('rejects a create submit missing a required applicable attribute', () => {
    const schema = buildCreateWorkOrderSchema(i18n.t.bind(i18n), [requiredTextAttribute()])
    const result = schema.safeParse({ ...VALID_BASE, attribute_values: { site_access: null } })

    expect(result.success).toBe(false)
    const issue =
      !result.success && result.error.issues.find((entry) => entry.path.join('.') === 'attribute_values.site_access')
    expect(issue).toBeDefined()
  })

  it('accepts a create submit with the required attribute filled in', () => {
    const schema = buildCreateWorkOrderSchema(i18n.t.bind(i18n), [requiredTextAttribute()])
    const result = schema.safeParse({ ...VALID_BASE, attribute_values: { site_access: 'Gate 3' } })

    expect(result.success).toBe(true)
  })

  it('rejects an update submit missing a required applicable attribute', () => {
    const schema = buildUpdateWorkOrderSchema(i18n.t.bind(i18n), [requiredTextAttribute()])
    const result = schema.safeParse({ ...VALID_BASE, attribute_values: { site_access: null } })

    expect(result.success).toBe(false)
  })

  it('no applicable attribute at all: an empty attribute_values map is valid', () => {
    const schema = buildCreateWorkOrderSchema(i18n.t.bind(i18n))
    const result = schema.safeParse(VALID_BASE)

    expect(result.success).toBe(true)
  })
})
