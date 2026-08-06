import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  buildCreateQuoteWorkflowSchema,
  buildUpdateQuoteWorkflowSchema,
} from '@/features/quote-workflows/quote-workflow-schema'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const VALID_PAYLOAD = {
  name: 'EMEA workflow',
  is_active: true,
  criteria: [{ field: 'state_id', value_id: 1 }],
}

describe('buildCreateQuoteWorkflowSchema', () => {
  it('accepts a valid payload', () => {
    const schema = buildCreateQuoteWorkflowSchema(i18n.t)
    expect(schema.safeParse(VALID_PAYLOAD).success).toBe(true)
  })

  it('rejects an empty name', () => {
    const schema = buildCreateQuoteWorkflowSchema(i18n.t)
    const result = schema.safeParse({ ...VALID_PAYLOAD, name: '' })
    expect(result.success).toBe(false)
  })

  it('rejects a name over 191 characters', () => {
    const schema = buildCreateQuoteWorkflowSchema(i18n.t)
    const result = schema.safeParse({ ...VALID_PAYLOAD, name: 'a'.repeat(192) })
    expect(result.success).toBe(false)
  })

  it('rejects an empty criteria array (min:1)', () => {
    const schema = buildCreateQuoteWorkflowSchema(i18n.t)
    const result = schema.safeParse({ ...VALID_PAYLOAD, criteria: [] })
    expect(result.success).toBe(false)
  })

  it('rejects a criterion row missing its field', () => {
    const schema = buildCreateQuoteWorkflowSchema(i18n.t)
    const result = schema.safeParse({
      ...VALID_PAYLOAD,
      criteria: [{ field: null, value_id: 1 }],
    })
    expect(result.success).toBe(false)
  })

  it('rejects a criterion row missing its value', () => {
    const schema = buildCreateQuoteWorkflowSchema(i18n.t)
    const result = schema.safeParse({
      ...VALID_PAYLOAD,
      criteria: [{ field: 'state_id', value_id: null }],
    })
    expect(result.success).toBe(false)
  })

  it('rejects two criteria rows sharing the same field', () => {
    const schema = buildCreateQuoteWorkflowSchema(i18n.t)
    const result = schema.safeParse({
      ...VALID_PAYLOAD,
      criteria: [
        { field: 'state_id', value_id: 1 },
        { field: 'state_id', value_id: 2 },
      ],
    })
    expect(result.success).toBe(false)
  })

  it('accepts multiple criteria rows with distinct fields', () => {
    const schema = buildCreateQuoteWorkflowSchema(i18n.t)
    const result = schema.safeParse({
      ...VALID_PAYLOAD,
      criteria: [
        { field: 'state_id', value_id: 1 },
        { field: 'source_id', value_id: 2 },
      ],
    })
    expect(result.success).toBe(true)
  })
})

describe('buildUpdateQuoteWorkflowSchema', () => {
  it('has the same shape as the create schema', () => {
    const schema = buildUpdateQuoteWorkflowSchema(i18n.t)
    expect(schema.safeParse(VALID_PAYLOAD).success).toBe(true)
  })
})
