import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildContractProgramSchema, contractProgramDefaultValues } from '@/features/contracts/contract-program-schema'
import type { ApplicableAttributeSummary } from '@/features/work-orders/types'

describe('buildContractProgramSchema (spec 0095 D-11, spec 0096 D-5)', () => {
  const schema = buildContractProgramSchema(i18n.t)
  /** The fields spec 0096 added to the dialog; spread into every case below. */
  const REQUIRED = { start_date: '2026-03-01', supervisor_ids: [21], attribute_values: {} }

  it('requires a title', () => {
    expect(schema.safeParse({ ...REQUIRED, title: '', type: 'processing', quote_line_ids: [1] }).success).toBe(false)
    expect(schema.safeParse({ ...REQUIRED, title: 'Installazione', type: 'processing', quote_line_ids: [1] }).success).toBe(true)
  })

  it('rejects a title over 191 characters', () => {
    expect(
      schema.safeParse({ ...REQUIRED, title: 'a'.repeat(192), type: 'processing', quote_line_ids: [1] }).success,
    ).toBe(false)
  })

  it('requires a valid type', () => {
    expect(
      schema.safeParse({ ...REQUIRED, title: 'Installazione', type: 'bogus', quote_line_ids: [1] }).success,
    ).toBe(false)
  })

  it('requires a start date and at least one responsabile (spec 0096, AC-060)', () => {
    expect(
      schema.safeParse({
        title: 'Installazione',
        type: 'processing',
        quote_line_ids: [1],
        supervisor_ids: [21],
        attribute_values: {},
      }).success,
    ).toBe(false)
    expect(
      schema.safeParse({
        title: 'Installazione',
        type: 'processing',
        quote_line_ids: [1],
        start_date: '2026-03-01',
        supervisor_ids: [],
        attribute_values: {},
      }).success,
    ).toBe(false)
  })

  it('requires at least one selected line (AC-061)', () => {
    expect(
      schema.safeParse({ ...REQUIRED, title: 'Installazione', type: 'processing', quote_line_ids: [] }).success,
    ).toBe(false)
    expect(
      schema.safeParse({ ...REQUIRED, title: 'Installazione', type: 'processing', quote_line_ids: [1, 2] }).success,
    ).toBe(true)
  })
})

/** Spec 0098 (AC-019): the same dynamic `attribute_values` shape/refine the work order form uses. */
describe('buildContractProgramSchema — attribute_values (spec 0098)', () => {
  const REQUIRED = {
    title: 'Installazione',
    type: 'processing' as const,
    start_date: '2026-03-01',
    supervisor_ids: [21],
    quote_line_ids: [1],
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

  it('rejects a submit missing a required applicable attribute', () => {
    const schema = buildContractProgramSchema(i18n.t, [requiredTextAttribute()])
    const result = schema.safeParse({ ...REQUIRED, attribute_values: { site_access: null } })

    expect(result.success).toBe(false)
    const issue =
      !result.success && result.error.issues.find((entry) => entry.path.join('.') === 'attribute_values.site_access')
    expect(issue).toBeDefined()
  })

  it('accepts a submit with the required attribute filled in', () => {
    const schema = buildContractProgramSchema(i18n.t, [requiredTextAttribute()])
    const result = schema.safeParse({ ...REQUIRED, attribute_values: { site_access: 'Gate 3' } })

    expect(result.success).toBe(true)
  })
})

describe('contractProgramDefaultValues', () => {
  it('defaults type to "processing", no selected lines and an empty attribute map', () => {
    expect(contractProgramDefaultValues()).toEqual({
      title: '',
      type: 'processing',
      start_date: '',
      supervisor_ids: [],
      quote_line_ids: [],
      attribute_values: {},
    })
  })
})
