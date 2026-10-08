import { z } from 'zod'
import type { TFunction } from 'i18next'

const PAYMENT_METHOD_CODE_MAX = 32
const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/

/**
 * Zod schema of the installment edit form, mirroring the server rules of spec
 * 0197 D-6 (date format and code length; `>= document_date` and code existence
 * stay server-side and come back as 422 on the field).
 */
export function buildInstallmentEditSchema(t: TFunction) {
  return z.object({
    due_date: z
      .string()
      .min(1, t('invoiceInstallments.edit.errors.dueDateRequired'))
      .regex(ISO_DATE, t('invoiceInstallments.edit.errors.dueDateInvalid')),
    payment_method_code: z
      .string()
      .max(PAYMENT_METHOD_CODE_MAX, t('invoiceInstallments.edit.errors.paymentMethodCodeMax')),
  })
}

export type InstallmentEditFormValues = z.infer<ReturnType<typeof buildInstallmentEditSchema>>
