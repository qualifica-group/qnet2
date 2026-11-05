import { z } from 'zod'
import type { TFunction } from 'i18next'
import {
  SUB_ACCOUNT_KINDS_BY_TYPE,
  TREASURY_DIRECTIONS,
  TREASURY_MOVEMENT_TYPES,
  type SubAccountKind,
} from '@/features/treasury-movements/types'

/** Backend `description` limit (1..255). */
export const DESCRIPTION_MAX_LENGTH = 255

/** Positive amount with at most two decimals; comma or dot as separator. */
const AMOUNT_PATTERN = /^\d+([.,]\d{1,2})?$/

/** Select value of a sub-account: `kind:id` (the three sources share no id space). */
export function subAccountValue(kind: SubAccountKind, id: number): string {
  return `${kind}:${id}`
}

export function parseSubAccountValue(value: string): { kind: SubAccountKind; id: number } | null {
  const [kind, rawId] = value.split(':')
  const id = Number(rawId)
  if ((kind === 'cost' || kind === 'revenue' || kind === 'financial') && Number.isInteger(id) && id > 0) {
    return { kind, id }
  }
  return null
}

export function normalizeAmount(value: string): string {
  return value.trim().replace(',', '.')
}

export function isPositiveAmount(value: string): boolean {
  const trimmed = value.trim()
  return AMOUNT_PATTERN.test(trimmed) && Number(normalizeAmount(trimmed)) > 0
}

/** Zod schema of the movement form, mirroring the server rules per type (spec 0360). */
export function buildTreasuryMovementSchema(t: TFunction) {
  return z
    .object({
      type: z.enum(TREASURY_MOVEMENT_TYPES),
      direction: z.enum([...TREASURY_DIRECTIONS, '']),
      movement_date: z.string(),
      financial_account_id: z.number().nullable(),
      counterpart_account_id: z.number().nullable(),
      amount: z.string(),
      description: z.string(),
      sub_account: z.string(),
      registry_id: z.number().nullable(),
      work_order_id: z.number().nullable(),
    })
    .superRefine((values, ctx) => {
      const issue = (path: keyof typeof values, key: string) =>
        ctx.addIssue({ code: 'custom', path: [path], message: t(`treasuryMovements.form.errors.${key}`) })

      if (values.movement_date === '') {
        issue('movement_date', 'dateRequired')
      }
      if (values.financial_account_id === null) {
        issue('financial_account_id', 'accountRequired')
      }
      if (values.type === 'transfer') {
        if (values.counterpart_account_id === null) {
          issue('counterpart_account_id', 'counterpartRequired')
        } else if (values.counterpart_account_id === values.financial_account_id) {
          issue('counterpart_account_id', 'counterpartSame')
        }
      }
      if (values.type === 'adjustment' && values.direction === '') {
        issue('direction', 'directionRequired')
      }
      if (!isPositiveAmount(values.amount)) {
        issue('amount', 'amountInvalid')
      }
      const description = values.description.trim()
      if (description === '') {
        issue('description', 'descriptionRequired')
      } else if (description.length > DESCRIPTION_MAX_LENGTH) {
        issue('description', 'descriptionTooLong')
      }
      const subAccount = parseSubAccountValue(values.sub_account)
      if (subAccount === null) {
        issue('sub_account', 'subAccountRequired')
      } else if (!SUB_ACCOUNT_KINDS_BY_TYPE[values.type].includes(subAccount.kind)) {
        issue('sub_account', 'subAccountNotAllowed')
      }
    })
}

export type TreasuryMovementFormValues = z.infer<ReturnType<typeof buildTreasuryMovementSchema>>
