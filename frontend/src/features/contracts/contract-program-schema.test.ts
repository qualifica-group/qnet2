import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  buildContractProgramSchema,
  contractProgramDefaultValues,
  toBatchPayload,
  type ContractProgramFormValues,
} from '@/features/contracts/contract-program-schema'
import { todayIsoDate } from '@/features/work-order-costs/work-order-costs-schema'

const schema = buildContractProgramSchema(i18n.t)

function group(overrides: Partial<ContractProgramFormValues['groups'][number]> = {}) {
  return {
    key: 'group-1',
    title: '',
    type: 'processing' as const,
    start_date: '2026-03-01',
    supervisor_ids: [21],
    task_template_id: null,
    quote_line_ids: [1],
    ...overrides,
  }
}

function values(groups: ContractProgramFormValues['groups']): ContractProgramFormValues {
  return { ...contractProgramDefaultValues(), groups }
}

describe('buildContractProgramSchema (spec 0215)', () => {
  it('accepts a group with a blank title: it is the automatic one', () => {
    expect(schema.safeParse(values([group()])).success).toBe(true)
  })

  it('requires at least one group', () => {
    expect(schema.safeParse(values([])).success).toBe(false)
  })

  it('rejects a title over 191 characters and an invalid type', () => {
    expect(schema.safeParse(values([group({ title: 'a'.repeat(192) })])).success).toBe(false)
    expect(schema.safeParse(values([group({ type: 'bogus' as never })])).success).toBe(false)
  })

  it('requires start date, supervisors and lines on every group, pointing at the failing group (AC-026)', () => {
    const result = schema.safeParse(
      values([group(), group({ start_date: '', supervisor_ids: [], quote_line_ids: [] })]),
    )

    expect(result.success).toBe(false)
    const paths = result.success ? [] : result.error.issues.map((issue) => issue.path.join('.'))
    expect(paths.sort()).toEqual(['groups.1.quote_line_ids', 'groups.1.start_date', 'groups.1.supervisor_ids'])
  })

  it('accepts an optional task template id', () => {
    expect(schema.safeParse(values([group({ task_template_id: 3 })])).success).toBe(true)
  })
})

describe('contractProgramDefaultValues', () => {
  it('starts with no groups and processing-type common values dated today', () => {
    expect(contractProgramDefaultValues()).toEqual({
      common: { type: 'processing', start_date: todayIsoDate(), supervisor_ids: [], task_template_id: null },
      groups: [],
    })
  })
})

describe('toBatchPayload (AC-024)', () => {
  it('sends null for a blank title, trims a typed one and drops the client key', () => {
    const payload = toBatchPayload(
      values([group({ title: '   ' }), group({ title: ' Impianto ', quote_line_ids: [2] })]),
    )

    expect(payload.groups.map((g) => g.title)).toEqual([null, 'Impianto'])
    expect(payload.groups[0]).toEqual({
      title: null,
      type: 'processing',
      start_date: '2026-03-01',
      supervisor_ids: [21],
      task_template_id: null,
      quote_line_ids: [1],
    })
  })
})
