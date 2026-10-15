import { describe, expect, it } from 'vitest'
import type { TFunction } from 'i18next'
import { buildAgentFiscalProfilePayload, EMPTY_FORM_VALUES } from '@/features/agent-fiscal-profiles/agent-fiscal-profile-form-payload'
import {
  buildAgentFiscalProfileSchema,
  type AgentFiscalProfileFormValues,
} from '@/features/agent-fiscal-profiles/agent-fiscal-profile-schema'

const t = ((key: string) => key) as unknown as TFunction
const schema = buildAgentFiscalProfileSchema(t)

function values(overrides: Partial<AgentFiscalProfileFormValues> = {}): AgentFiscalProfileFormValues {
  return {
    ...EMPTY_FORM_VALUES,
    referent_id: 5,
    withholding_rate: '23.00',
    withholding_base_percent: '50',
    valid_from: '2026-01-01',
    ...overrides,
  }
}

function issuePaths(input: AgentFiscalProfileFormValues): string[] {
  const result = schema.safeParse(input)
  return result.success ? [] : result.error.issues.map((issue) => issue.path.join('.'))
}

describe('agent fiscal profile schema (spec 0212 D-8, AC-020)', () => {
  it('accepts a complete profile with withholding and no ENASARCO', () => {
    expect(schema.safeParse(values()).success).toBe(true)
  })

  it('requires the agent and the start date', () => {
    expect(issuePaths(values({ referent_id: null, valid_from: '' }))).toEqual(['referent_id', 'valid_from'])
  })

  it('requires both percentages while the withholding is enabled', () => {
    expect(issuePaths(values({ withholding_rate: '', withholding_base_percent: '' }))).toEqual([
      'withholding_rate',
      'withholding_base_percent',
    ])
  })

  it('ignores the percentages while the withholding is disabled', () => {
    expect(
      schema.safeParse(values({ withholding_enabled: false, withholding_rate: '', withholding_base_percent: 'abc' }))
        .success,
    ).toBe(true)
  })

  it.each(['100.01', '-1', '12.345', 'abc'])('rejects the out-of-range or malformed percentage %s', (rate) => {
    expect(issuePaths(values({ withholding_rate: rate }))).toEqual(['withholding_rate'])
  })

  it.each(['0', '100', '23.5', '99.99'])('accepts the percentage %s', (rate) => {
    expect(schema.safeParse(values({ withholding_rate: rate })).success).toBe(true)
  })

  it('requires the mandate type only while ENASARCO is enabled', () => {
    expect(issuePaths(values({ enasarco_enabled: true, enasarco_mandate_type: '' }))).toEqual([
      'enasarco_mandate_type',
    ])
    expect(schema.safeParse(values({ enasarco_enabled: true, enasarco_mandate_type: 'mono' })).success).toBe(true)
    expect(schema.safeParse(values({ enasarco_enabled: false, enasarco_mandate_type: '' })).success).toBe(true)
  })

  it('rejects an end date before the start date and accepts an equal or open one', () => {
    expect(issuePaths(values({ valid_until: '2025-12-31' }))).toEqual(['valid_until'])
    expect(schema.safeParse(values({ valid_until: '2026-01-01' })).success).toBe(true)
    expect(schema.safeParse(values({ valid_until: '' })).success).toBe(true)
  })

  it('rejects notes over 2000 characters', () => {
    expect(issuePaths(values({ notes: 'x'.repeat(2001) }))).toEqual(['notes'])
  })
})

describe('buildAgentFiscalProfilePayload', () => {
  it('sends the conditional fields as null when their switch is off', () => {
    const parsed = schema.parse(
      values({ withholding_enabled: false, enasarco_enabled: false, enasarco_mandate_type: 'pluri' }),
    )

    expect(buildAgentFiscalProfilePayload(parsed)).toEqual({
      referent_id: 5,
      withholding_enabled: false,
      withholding_rate: null,
      withholding_base_percent: null,
      enasarco_enabled: false,
      enasarco_mandate_type: null,
      vat_rate_id: null,
      payment_method_id: null,
      cost_type_id: null,
      valid_from: '2026-01-01',
      valid_until: null,
      notes: null,
    })
  })

  it('carries the optional payment method and cost type defaults (spec 0267 D-8)', () => {
    const parsed = schema.parse(values({ payment_method_id: 3, cost_type_id: 8 }))

    expect(buildAgentFiscalProfilePayload(parsed)).toMatchObject({ payment_method_id: 3, cost_type_id: 8 })
  })

  it('sends numeric rates, the mandate type and trimmed notes when enabled', () => {
    const parsed = schema.parse(
      values({ enasarco_enabled: true, enasarco_mandate_type: 'mono', valid_until: '2026-12-31', notes: ' ok ' }),
    )

    expect(buildAgentFiscalProfilePayload(parsed)).toMatchObject({
      withholding_rate: 23,
      withholding_base_percent: 50,
      enasarco_enabled: true,
      enasarco_mandate_type: 'mono',
      vat_rate_id: null,
      valid_until: '2026-12-31',
      notes: 'ok',
    })
  })
})
