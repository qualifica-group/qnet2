import { describe, expect, it } from 'vitest'
import { buildRenewalDefaults } from '@/features/contracts/contract-renewal-defaults'
import type { ContractRenewal } from '@/features/contracts/types'

const TODAY = new Date('2026-11-05T10:00:00Z')

const base = {
  renewals: [] as ContractRenewal[],
  accepted_at: '2026-01-01' as string | null,
  validated_at: null as string | null,
  renewal_date: '2026-11-30' as string | null,
  expiry_date: '2026-12-31' as string | null,
}

describe('buildRenewalDefaults (AC-026)', () => {
  it('shifts expiry and renewal dates by the inclusive duration of the period', () => {
    expect(buildRenewalDefaults(base, TODAY)).toEqual({ expiry_date: '2027-12-31', renewal_date: '2027-11-30' })
  })

  it('leaves the renewal date empty when the contract has none', () => {
    expect(buildRenewalDefaults({ ...base, renewal_date: null }, TODAY)).toEqual({
      expiry_date: '2027-12-31',
      renewal_date: '',
    })
  })

  it('falls back to today + 12 months, no renewal date, without a start date', () => {
    expect(buildRenewalDefaults({ ...base, accepted_at: null }, TODAY)).toEqual({
      expiry_date: '2027-11-05',
      renewal_date: '',
    })
  })

  it('falls back to today + 12 months without an expiry date', () => {
    expect(buildRenewalDefaults({ ...base, expiry_date: null }, TODAY).expiry_date).toBe('2027-11-05')
  })

  it('uses validated_at when acceptance is missing', () => {
    const result = buildRenewalDefaults({ ...base, accepted_at: null, validated_at: '2026-01-01' }, TODAY)
    expect(result.expiry_date).toBe('2027-12-31')
  })

  it('starts the period at the latest renewal', () => {
    const renewal = { starts_on: '2027-01-01' } as ContractRenewal
    const result = buildRenewalDefaults(
      { ...base, renewals: [renewal], expiry_date: '2027-12-31', renewal_date: '2027-11-30' },
      TODAY,
    )
    expect(result).toEqual({ expiry_date: '2028-12-30', renewal_date: '2028-11-29' })
  })
})
