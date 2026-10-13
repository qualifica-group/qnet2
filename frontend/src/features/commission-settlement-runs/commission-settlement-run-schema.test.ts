import { describe, expect, it } from 'vitest'
import type { TFunction } from 'i18next'
import {
  buildCommissionSettlementRunPayload,
  buildDefaultRunValues,
  previousMonthRange,
} from '@/features/commission-settlement-runs/commission-settlement-run-payload'
import {
  buildCommissionSettlementRunSchema,
  type CommissionSettlementRunFormValues,
} from '@/features/commission-settlement-runs/commission-settlement-run-schema'

const t = ((key: string) => key) as unknown as TFunction
const schema = buildCommissionSettlementRunSchema(t)

function values(overrides: Partial<CommissionSettlementRunFormValues> = {}): CommissionSettlementRunFormValues {
  return { ...buildDefaultRunValues(new Date(2026, 9, 13)), ...overrides }
}

describe('commission settlement run form (spec 0213 AC-020)', () => {
  it('proposes the previous calendar month', () => {
    expect(previousMonthRange(new Date(2026, 9, 13))).toEqual({ from: '2026-09-01', to: '2026-09-30' })
    expect(previousMonthRange(new Date(2026, 0, 5))).toEqual({ from: '2025-12-01', to: '2025-12-31' })
  })

  it('rejects a period_to before period_from on period_to', () => {
    const result = schema.safeParse(values({ period_from: '2026-09-10', period_to: '2026-09-01' }))

    expect(result.success).toBe(false)
    expect(result.success ? [] : result.error.issues.map((issue) => issue.path.join('.'))).toEqual(['period_to'])
  })

  it('validates the optional minimum amount', () => {
    expect(schema.safeParse(values({ min_amount: '' })).success).toBe(true)
    expect(schema.safeParse(values({ min_amount: '50.5' })).success).toBe(true)
    expect(schema.safeParse(values({ min_amount: '-1' })).success).toBe(false)
  })

  it('sends empty filters as null and the contract body otherwise', () => {
    expect(buildCommissionSettlementRunPayload(schema.parse(values()))).toEqual({
      period_from: '2026-09-01',
      period_to: '2026-09-30',
      company_id: null,
      referent_ids: null,
      recipient_roles: null,
      min_amount: null,
    })
    expect(
      buildCommissionSettlementRunPayload(
        schema.parse(values({ company_id: 3, referent_ids: [4, 5], recipient_roles: ['COMMERCIAL'], min_amount: '25' })),
      ),
    ).toEqual({
      period_from: '2026-09-01',
      period_to: '2026-09-30',
      company_id: 3,
      referent_ids: [4, 5],
      recipient_roles: ['COMMERCIAL'],
      min_amount: 25,
    })
  })
})
