import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  buildEditContractSchema,
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
  const schema = buildEditContractSchema()

  it('accepts the four editable fields, all nullable — the status is not part of the form', () => {
    expect(
      schema.safeParse({
        renewal_date: null,
        expiry_date: null,
        payment_notes: null,
        comments: null,
      }).success,
    ).toBe(true)
    expect(schema.safeParse({ renewal_date: '2026-12-31' }).success).toBe(false)
  })
})
