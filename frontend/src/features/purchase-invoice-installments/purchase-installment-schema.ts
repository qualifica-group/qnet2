import { z } from 'zod'
import type { TFunction } from 'i18next'

const PAYMENT_METHOD_CODE_MAX = 32
const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/

/**
 * Zod schema of the installment edit form, mirroring the server rules of spec
 * 0211 D-7 (date format; `>= document_date` and code existence stay
 * server-side and come back as 422 on the field). The code comes from a
 * select, so only its length is checked here.
 */
export function buildPurchaseInstallmentEditSchema(t: TFunction) {
  return z.object({
    due_date: z
      .string()
      .min(1, t('purchaseInvoiceInstallments.edit.errors.dueDateRequired'))
      .regex(ISO_DATE, t('purchaseInvoiceInstallments.edit.errors.dueDateInvalid')),
    payment_method_code: z.string().max(PAYMENT_METHOD_CODE_MAX),
  })
}

export type PurchaseInstallmentEditFormValues = z.infer<ReturnType<typeof buildPurchaseInstallmentEditSchema>>
