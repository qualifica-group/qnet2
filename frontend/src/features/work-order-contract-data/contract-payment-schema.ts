import { z } from 'zod'
import type { TFunction } from 'i18next'
import type { ContractDataLine, UpdateContractLinePayload } from '@/features/work-order-contract-data/types'

/** Backend `payment_agreement` limit (`max:2000`). */
export const PAYMENT_AGREEMENT_MAX_LENGTH = 2000

/** Zod schema of the per-line payment editor; mirrors the PATCH validation (spec 0201). */
export function buildContractPaymentSchema(t: TFunction) {
  return z.object({
    work_order_payment_status_id: z.number().int().positive().nullable(),
    payment_agreement: z
      .string()
      .max(PAYMENT_AGREEMENT_MAX_LENGTH, t('workOrders.contractData.editor.agreementMax')),
    has_unpaid: z.boolean(),
  })
}

export type ContractPaymentFormValues = z.infer<ReturnType<typeof buildContractPaymentSchema>>

/** Starting values of the editor, from the persisted payment of the line. */
export function paymentFormDefaults(line: ContractDataLine): ContractPaymentFormValues {
  return {
    work_order_payment_status_id: line.payment.status?.id ?? null,
    payment_agreement: line.payment.payment_agreement ?? '',
    has_unpaid: line.payment.has_unpaid,
  }
}

/** Diff payload: only what changed leaves the browser; an emptied agreement is sent as `null`. */
export function buildContractPaymentPayload(
  values: ContractPaymentFormValues,
  line: ContractDataLine,
): UpdateContractLinePayload {
  const payload: UpdateContractLinePayload = {}
  const original = paymentFormDefaults(line)

  if (values.work_order_payment_status_id !== original.work_order_payment_status_id) {
    payload.work_order_payment_status_id = values.work_order_payment_status_id
  }
  if (values.payment_agreement !== original.payment_agreement) {
    payload.payment_agreement = values.payment_agreement === '' ? null : values.payment_agreement
  }
  if (values.has_unpaid !== original.has_unpaid) {
    payload.has_unpaid = values.has_unpaid
  }

  return payload
}
