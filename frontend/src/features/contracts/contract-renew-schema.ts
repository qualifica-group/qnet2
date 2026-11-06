import { z } from 'zod'
import type { TFunction } from 'i18next'

/**
 * "Rinnova" (spec 0358): the new expiry is mandatory, the renewal date
 * optional and never later than the expiry. The server re-checks both and
 * additionally requires the expiry to follow the current one.
 */
export function buildRenewContractSchema(t: TFunction) {
  return z
    .object({
      renewal_date: z.string(),
      expiry_date: z.string().min(1, t('contracts.actions.renewDialog.expiryRequired')),
    })
    .refine((value) => value.renewal_date === '' || value.expiry_date === '' || value.renewal_date <= value.expiry_date, {
      message: t('contracts.actions.renewDialog.renewalAfterExpiry'),
      path: ['renewal_date'],
    })
}

export type RenewContractFormValues = z.infer<ReturnType<typeof buildRenewContractSchema>>
