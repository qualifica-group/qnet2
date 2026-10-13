import { describe, expect, it } from 'vitest'
import type { TFunction } from 'i18next'
import {
  buildEnasarcoParameterPayload,
  EMPTY_FORM_VALUES,
} from '@/features/enasarco-parameters/enasarco-parameter-form-payload'
import {
  buildEnasarcoParameterSchema,
  type EnasarcoParameterFormValues,
} from '@/features/enasarco-parameters/enasarco-parameter-schema'

const t = ((key: string) => key) as unknown as TFunction
const schema = buildEnasarcoParameterSchema(t)

function values(overrides: Partial<EnasarcoParameterFormValues> = {}): EnasarcoParameterFormValues {
  return {
    ...EMPTY_FORM_VALUES,
    year: '2026',
    total_rate: '17.00',
    agent_share_percent: '50',
    single_firm_ceiling: '13000',
    multi_firm_ceiling: '26000.50',
    ...overrides,
  }
}

function issuePaths(input: EnasarcoParameterFormValues): string[] {
  const result = schema.safeParse(input)
  return result.success ? [] : result.error.issues.map((issue) => issue.path.join('.'))
}

describe('enasarco parameter schema (spec 0213 AC-023)', () => {
  it('accepts a complete parameter set', () => {
    expect(schema.safeParse(values()).success).toBe(true)
  })

  it.each(['1999', '2101', 'abc', '20.5'])('rejects the year %s', (year) => {
    expect(issuePaths(values({ year }))).toEqual(['year'])
  })

  it.each(['1999', '2101', ''])('requires a year between 2000 and 2100 (%s)', (year) => {
    expect(issuePaths(values({ year })).includes('year')).toBe(true)
  })

  it('keeps percentages within 0..100 with at most 2 decimals', () => {
    expect(issuePaths(values({ total_rate: '100.01' }))).toEqual(['total_rate'])
    expect(issuePaths(values({ agent_share_percent: '12.345' }))).toEqual(['agent_share_percent'])
    expect(schema.safeParse(values({ total_rate: '0', agent_share_percent: '100' })).success).toBe(true)
  })

  it('rejects negative or over-precise ceilings', () => {
    expect(issuePaths(values({ single_firm_ceiling: '-1' }))).toEqual(['single_firm_ceiling'])
    expect(issuePaths(values({ multi_firm_ceiling: '10.999' }))).toEqual(['multi_firm_ceiling'])
    expect(schema.safeParse(values({ single_firm_ceiling: '0' })).success).toBe(true)
  })

  it('builds numeric payloads and nulls the empty notes', () => {
    const parsed = schema.parse(values({ notes: '  ' }))

    expect(buildEnasarcoParameterPayload(parsed)).toEqual({
      year: 2026,
      total_rate: 17,
      agent_share_percent: 50,
      single_firm_ceiling: 13000,
      multi_firm_ceiling: 26000.5,
      notes: null,
    })
  })
})
