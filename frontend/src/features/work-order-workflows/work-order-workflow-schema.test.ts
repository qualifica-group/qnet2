import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildWorkOrderWorkflowSchema } from '@/features/work-order-workflows/work-order-workflow-schema'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const VALID_PAYLOAD = {
  name: 'SOA set',
  is_active: true,
  criteria: [{ field: 'business_function_id', value_id: 1 }],
  statuses: [],
}

describe('buildWorkOrderWorkflowSchema', () => {
  const schema = buildWorkOrderWorkflowSchema(i18n.t)

  it('accepts a valid payload', () => {
    expect(schema.safeParse(VALID_PAYLOAD).success).toBe(true)
  })

  it('rejects an empty name and a name over 191 characters', () => {
    expect(schema.safeParse({ ...VALID_PAYLOAD, name: '' }).success).toBe(false)
    expect(schema.safeParse({ ...VALID_PAYLOAD, name: 'a'.repeat(192) }).success).toBe(false)
    expect(schema.safeParse({ ...VALID_PAYLOAD, name: 'a'.repeat(191) }).success).toBe(true)
  })

  it('rejects a status without a name', () => {
    const row = {
      id: 'c1',
      name: '  ',
      description: null,
      color: null,
      group: 'in_progress',
      system_key: null,
      progress_percentage: null,
    }
    expect(schema.safeParse({ ...VALID_PAYLOAD, statuses: [row] }).success).toBe(false)
    expect(schema.safeParse({ ...VALID_PAYLOAD, statuses: [{ ...row, name: 'Review' }] }).success).toBe(true)
  })

  it('rejects an empty criteria array (min:1)', () => {
    expect(schema.safeParse({ ...VALID_PAYLOAD, criteria: [] }).success).toBe(false)
  })

  it('rejects a criterion row missing its field or its value', () => {
    expect(schema.safeParse({ ...VALID_PAYLOAD, criteria: [{ field: null, value_id: 1 }] }).success).toBe(false)
    expect(
      schema.safeParse({ ...VALID_PAYLOAD, criteria: [{ field: 'business_function_id', value_id: null }] }).success,
    ).toBe(false)
  })

  it('rejects two criteria rows sharing the same field and accepts distinct ones', () => {
    const duplicate = [
      { field: 'business_function_id', value_id: 1 },
      { field: 'business_function_id', value_id: 2 },
    ]
    const distinct = [
      { field: 'business_function_id', value_id: 1 },
      { field: 'product_category_id', value_id: 2 },
    ]
    expect(schema.safeParse({ ...VALID_PAYLOAD, criteria: duplicate }).success).toBe(false)
    expect(schema.safeParse({ ...VALID_PAYLOAD, criteria: distinct }).success).toBe(true)
  })
})
