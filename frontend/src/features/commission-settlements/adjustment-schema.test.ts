import { describe, expect, it } from 'vitest'
import type { TFunction } from 'i18next'
import {
  buildAdjustmentCreatePayload,
  buildAdjustmentDefaults,
  buildAdjustmentUpdatePayload,
} from '@/features/commission-settlements/adjustment-payload'
import { buildAdjustmentSchema, type AdjustmentFormValues } from '@/features/commission-settlements/adjustment-schema'

const t = ((key: string) => key) as unknown as TFunction

function values(overrides: Partial<AdjustmentFormValues> = {}): AdjustmentFormValues {
  return { referent_id: 5, company_id: 2, amount: '-12.50', accrued_on: '2026-10-01', notes: 'Correzione', ...overrides }
}

function issuePaths(schema: ReturnType<typeof buildAdjustmentSchema>, input: AdjustmentFormValues): string[] {
  const result = schema.safeParse(input)
  return result.success ? [] : result.error.issues.map((issue) => issue.path.join('.'))
}

describe('adjustment schema (spec 0213 AC-022)', () => {
  const draftSchema = buildAdjustmentSchema(t, false)

  it('accepts a signed amount with up to 2 decimals', () => {
    expect(draftSchema.safeParse(values({ amount: '-12.50' })).success).toBe(true)
    expect(draftSchema.safeParse(values({ amount: '100' })).success).toBe(true)
  })

  it.each(['0', '0.00', '-0', '', '1.234', 'abc'])('rejects the amount "%s"', (amount) => {
    expect([...new Set(issuePaths(draftSchema, values({ amount })))]).toEqual(['amount'])
  })

  it('requires the date and the notes, notes max 500', () => {
    expect(issuePaths(draftSchema, values({ accrued_on: '' }))).toEqual(['accrued_on'])
    expect(issuePaths(draftSchema, values({ notes: '   ' }))).toEqual(['notes'])
    expect(issuePaths(draftSchema, values({ notes: 'x'.repeat(501) }))).toEqual(['notes'])
    expect(draftSchema.safeParse(values({ notes: 'x'.repeat(500) })).success).toBe(true)
  })

  it('requires agent and company only in standalone mode', () => {
    const standalone = buildAdjustmentSchema(t, true)

    expect(issuePaths(standalone, values({ referent_id: null, company_id: null }))).toEqual(['referent_id', 'company_id'])
    expect(draftSchema.safeParse(values({ referent_id: null, company_id: null })).success).toBe(true)
  })
})

describe('adjustment payloads', () => {
  const schema = buildAdjustmentSchema(t, true)

  it('sends commission_settlement_id inside a draft and null when standalone', () => {
    const parsed = schema.parse(values())

    expect(buildAdjustmentCreatePayload(parsed, 9)).toEqual({
      referent_id: 5,
      company_id: 2,
      amount: -12.5,
      accrued_on: '2026-10-01',
      notes: 'Correzione',
      commission_settlement_id: 9,
    })
    expect(buildAdjustmentCreatePayload(parsed, null).commission_settlement_id).toBeNull()
    expect(buildAdjustmentUpdatePayload(parsed)).toEqual({ amount: -12.5, accrued_on: '2026-10-01', notes: 'Correzione' })
  })

  it('fixes agent and company from the draft and prefills the edit mode', () => {
    const draft = buildAdjustmentDefaults({
      type: 'draft',
      settlementId: 9,
      referent: { id: 5, name: 'Mario' },
      company: { id: 2, name: 'Acme' },
    })
    const edit = buildAdjustmentDefaults({ type: 'edit', adjustmentId: 3, amount: '10.00', accruedOn: '2026-09-30', notes: 'n' })

    expect([draft.referent_id, draft.company_id]).toEqual([5, 2])
    expect([edit.amount, edit.accrued_on, edit.notes]).toEqual(['10.00', '2026-09-30', 'n'])
  })
})
