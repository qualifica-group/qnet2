import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  buildEditContractSchema,
  buildScheduleContractSchema,
  buildTerminateContractSchema,
  buildValidateContractSchema,
} from '@/features/contracts/contract-schema'

describe('buildValidateContractSchema', () => {
  const schema = buildValidateContractSchema(i18n.t)

  it('accepts today, rejects a future date', () => {
    expect(schema.safeParse({ validated_at: '2020-01-01', contract_status_id: null }).success).toBe(true)
    expect(schema.safeParse({ validated_at: '2999-01-01', contract_status_id: null }).success).toBe(false)
  })

  it('requires a date', () => {
    expect(schema.safeParse({ validated_at: '', contract_status_id: null }).success).toBe(false)
  })
})

describe('buildScheduleContractSchema', () => {
  const schema = buildScheduleContractSchema(i18n.t)

  it('requires expiry_date and contract_status_id', () => {
    expect(schema.safeParse({ expiry_date: '', renewal_date: null, contract_status_id: null }).success).toBe(false)
    expect(schema.safeParse({ expiry_date: '2020-12-31', renewal_date: null, contract_status_id: null }).success).toBe(
      false,
    )
    expect(
      schema.safeParse({ expiry_date: '2020-12-31', renewal_date: null, contract_status_id: 5 }).success,
    ).toBe(true)
  })

  it('rejects a renewal_date after expiry_date', () => {
    const result = schema.safeParse({
      expiry_date: '2020-06-01',
      renewal_date: '2020-07-01',
      contract_status_id: 5,
    })
    expect(result.success).toBe(false)
  })

  it('accepts a renewal_date on or before expiry_date', () => {
    const result = schema.safeParse({
      expiry_date: '2020-06-01',
      renewal_date: '2020-05-01',
      contract_status_id: 5,
    })
    expect(result.success).toBe(true)
  })
})

describe('buildTerminateContractSchema', () => {
  const schema = buildTerminateContractSchema(i18n.t)

  it('requires terminated_at and termination_reason', () => {
    expect(
      schema.safeParse({ terminated_at: '', termination_reason: '', contract_status_id: null }).success,
    ).toBe(false)
    expect(
      schema.safeParse({ terminated_at: '2020-01-01', termination_reason: 'reason', contract_status_id: null })
        .success,
    ).toBe(true)
  })

  it('rejects a future terminated_at', () => {
    expect(
      schema.safeParse({ terminated_at: '2999-01-01', termination_reason: 'reason', contract_status_id: null })
        .success,
    ).toBe(false)
  })

  it('rejects a reason over 2000 characters', () => {
    expect(
      schema.safeParse({
        terminated_at: '2020-01-01',
        termination_reason: 'a'.repeat(2001),
        contract_status_id: null,
      }).success,
    ).toBe(false)
  })
})

describe('buildEditContractSchema', () => {
  const schema = buildEditContractSchema(i18n.t)

  it('requires a non-null contract_status_id (the FK is NOT NULL)', () => {
    expect(
      schema.safeParse({
        contract_status_id: null,
        renewal_date: null,
        expiry_date: null,
        payment_notes: null,
        comments: null,
      }).success,
    ).toBe(false)
    expect(
      schema.safeParse({
        contract_status_id: 1,
        renewal_date: null,
        expiry_date: null,
        payment_notes: null,
        comments: null,
      }).success,
    ).toBe(true)
  })
})
