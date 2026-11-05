import { describe, expect, it } from 'vitest'
import { buildRevenueTypePayload, buildUpdateRevenueTypePayload } from '@/features/chart-of-accounts/chart-account-payload'
import type { RevenueTypeFormValues } from '@/features/chart-of-accounts/chart-account-schema'

const VALUES: RevenueTypeFormValues = {
  code: '70.01.001',
  name: 'Consulting',
  description: null,
  is_active: true,
  chart_account_id: 4,
  is_institution_commission_default: true,
}

const ORIGINAL = { code: '70.01.001', name: 'Consulting', description: null, is_active: true, chart_account_id: 4 }

describe('revenue sub-account payload (spec 0350 default for Entity commissions)', () => {
  it('sends the flag on create only when switched on', () => {
    expect(buildRevenueTypePayload(VALUES)).toMatchObject({ is_institution_commission_default: true })
    expect(buildRevenueTypePayload({ ...VALUES, is_institution_commission_default: false })).not.toHaveProperty(
      'is_institution_commission_default',
    )
  })

  it('sends the flag on update only when it changed', () => {
    expect(buildUpdateRevenueTypePayload(VALUES, ORIGINAL)).toEqual({ is_institution_commission_default: true })
    expect(buildUpdateRevenueTypePayload(VALUES, { ...ORIGINAL, is_institution_commission_default: true })).toEqual({})
    expect(
      buildUpdateRevenueTypePayload({ ...VALUES, is_institution_commission_default: false }, { ...ORIGINAL, is_institution_commission_default: true }),
    ).toEqual({ is_institution_commission_default: false })
  })
})
