import { z } from 'zod'
import type { TFunction } from 'i18next'
import { parseCents } from '@/features/account-statements/reconciliation-math'

/** Backend `note` limit. */
export const NOTE_MAX_LENGTH = 255

/**
 * Zod schema of the "New reconciliation" form (spec 0363). `adjustmentAccountRequired` is true when the
 * difference is not zero, which makes the financial sub-account mandatory for an adjustment.
 */
export function buildReconciliationSchema(t: TFunction, adjustmentAccountRequired: boolean) {
  return z
    .object({
      statement_date: z.string(),
      bank_balance: z.string(),
      note: z.string(),
      create_adjustment: z.boolean(),
      treasury_type_id: z.string(),
    })
    .superRefine((values, ctx) => {
      const issue = (path: keyof typeof values, key: string) =>
        ctx.addIssue({ code: 'custom', path: [path], message: t(`accountStatements.reconciliation.errors.${key}`) })

      if (values.statement_date === '') {
        issue('statement_date', 'dateRequired')
      }
      if (parseCents(values.bank_balance) === null) {
        issue('bank_balance', 'balanceInvalid')
      }
      if (values.note.length > NOTE_MAX_LENGTH) {
        issue('note', 'noteTooLong')
      }
      if (values.create_adjustment && adjustmentAccountRequired && values.treasury_type_id === '') {
        issue('treasury_type_id', 'accountRequired')
      }
    })
}

export type ReconciliationFormValues = z.infer<ReturnType<typeof buildReconciliationSchema>>

export const EMPTY_RECONCILIATION_VALUES: ReconciliationFormValues = {
  statement_date: '',
  bank_balance: '',
  note: '',
  create_adjustment: false,
  treasury_type_id: '',
}
