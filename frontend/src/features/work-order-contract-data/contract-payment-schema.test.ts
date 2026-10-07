import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  buildContractPaymentPayload,
  buildContractPaymentSchema,
  paymentFormDefaults,
  PAYMENT_AGREEMENT_MAX_LENGTH,
} from '@/features/work-order-contract-data/contract-payment-schema'
import { PAID_LINE, RECEIVED_LINE } from '@/features/work-order-contract-data/contract-data-fixtures'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('buildContractPaymentSchema', () => {
  const schema = () => buildContractPaymentSchema(i18n.t)
  const valid = { work_order_payment_status_id: 3, payment_agreement: 'Saldo', has_unpaid: false }

  it('accepts a status id, a null status and an empty agreement', () => {
    expect(schema().safeParse(valid).success).toBe(true)
    expect(schema().safeParse({ ...valid, work_order_payment_status_id: null, payment_agreement: '' }).success).toBe(true)
  })

  it('rejects an agreement over the 2000 characters limit', () => {
    expect(schema().safeParse({ ...valid, payment_agreement: 'a'.repeat(PAYMENT_AGREEMENT_MAX_LENGTH + 1) }).success).toBe(false)
  })

  it('rejects a non-boolean unpaid flag and a non-positive status id', () => {
    expect(schema().safeParse({ ...valid, has_unpaid: 'yes' }).success).toBe(false)
    expect(schema().safeParse({ ...valid, work_order_payment_status_id: 0 }).success).toBe(false)
  })
})

describe('paymentFormDefaults / buildContractPaymentPayload', () => {
  it('starts from the persisted payment, an absent agreement becoming an empty string', () => {
    expect(paymentFormDefaults(PAID_LINE)).toEqual({
      work_order_payment_status_id: null,
      payment_agreement: '',
      has_unpaid: false,
    })
    expect(paymentFormDefaults(RECEIVED_LINE)).toEqual({
      work_order_payment_status_id: 3,
      payment_agreement: 'Saldo a 30 giorni',
      has_unpaid: true,
    })
  })

  it('sends nothing when nothing changed', () => {
    expect(buildContractPaymentPayload(paymentFormDefaults(RECEIVED_LINE), RECEIVED_LINE)).toEqual({})
  })

  it('sends only the changed keys', () => {
    const values = { ...paymentFormDefaults(RECEIVED_LINE), has_unpaid: false }
    expect(buildContractPaymentPayload(values, RECEIVED_LINE)).toEqual({ has_unpaid: false })
  })

  it('sends a cleared status and an emptied agreement as null', () => {
    const values = { work_order_payment_status_id: null, payment_agreement: '', has_unpaid: true }
    expect(buildContractPaymentPayload(values, RECEIVED_LINE)).toEqual({
      work_order_payment_status_id: null,
      payment_agreement: null,
    })
  })
})
