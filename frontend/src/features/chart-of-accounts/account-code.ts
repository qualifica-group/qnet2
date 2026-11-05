import { z } from 'zod'
import type { TFunction } from 'i18next'

/** Backend limit of every ledger code (spec 0348 D-5): mastri, conti and sottoconti share it. */
export const ACCOUNT_CODE_MAX_LENGTH = 20

/** Free-form but restricted charset (spec 0348 D-5); uniqueness across the whole plan is enforced server-side. */
export const ACCOUNT_CODE_PATTERN = /^[A-Za-z0-9.-]+$/

/** Required ledger code, localized messages. */
export function buildAccountCodeSchema(t: TFunction) {
  return z
    .string()
    .trim()
    .min(1, t('chartOfAccounts.form.codeRequired'))
    .max(ACCOUNT_CODE_MAX_LENGTH, t('chartOfAccounts.form.codeMax'))
    .regex(ACCOUNT_CODE_PATTERN, t('chartOfAccounts.form.codeInvalid'))
}
