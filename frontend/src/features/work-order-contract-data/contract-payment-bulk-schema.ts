import { z } from 'zod'
import type { TFunction } from 'i18next'
import type { BulkUpdateContractLinesPayload } from '@/features/work-order-contract-data/types'

/** Select value: leave the field of each line as it is (the key is not sent). */
export const KEEP_VALUE = 'keep'
/** Status select value standing for "no status" (`null`): Radix items cannot carry an empty value. */
export const NO_STATUS_VALUE = 'none'

export const UNPAID_VALUES = [KEEP_VALUE, 'yes', 'no'] as const

/**
 * Zod schema of the bulk payment dialog (spec 0333, D-1): each field is
 * "keep unchanged" or a value, at least one must be a value (the server's
 * own "choose at least one value" rule).
 */
export function buildBulkPaymentSchema(t: TFunction) {
  return z
    .object({
      status: z.string(),
      unpaid: z.enum(UNPAID_VALUES),
    })
    .refine((values) => values.status !== KEEP_VALUE || values.unpaid !== KEEP_VALUE, {
      message: t('workOrders.contractData.bulk.nothingToSet'),
      path: ['status'],
    })
}

export type BulkPaymentFormValues = z.infer<ReturnType<typeof buildBulkPaymentSchema>>

export const BULK_PAYMENT_DEFAULTS: BulkPaymentFormValues = { status: KEEP_VALUE, unpaid: KEEP_VALUE }

/** Only the fields set to a value leave the browser. */
export function buildBulkPaymentPayload(values: BulkPaymentFormValues, quoteLineIds: number[]): BulkUpdateContractLinesPayload {
  const payload: BulkUpdateContractLinesPayload = { quote_line_ids: quoteLineIds }

  if (values.status !== KEEP_VALUE) {
    payload.work_order_payment_status_id = values.status === NO_STATUS_VALUE ? null : Number(values.status)
  }
  if (values.unpaid !== KEEP_VALUE) {
    payload.has_unpaid = values.unpaid === 'yes'
  }

  return payload
}
