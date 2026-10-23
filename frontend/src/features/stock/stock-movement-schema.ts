import { z } from 'zod'
import type { TFunction } from 'i18next'
import { dayOf } from '@/features/absences/calendar/calendar-month'
import { isPositiveQuantity, isValidQuantity } from '@/features/stock/quantity'
import { STOCK_MOVEMENT_TYPES, type StockMovementType } from '@/features/stock/types'

/** Backend `note` limit (`max:1000`, spec 0307 D-14). */
export const NOTE_MAX_LENGTH = 1000

/** Types whose destination is a second site (TRANSFER) or whose holder must be named (ASSIGN, RETURN). */
const TYPES_REQUIRING_USER: readonly StockMovementType[] = ['ASSIGN', 'RETURN']
const TYPES_REQUIRING_NOTE: readonly StockMovementType[] = ['UNLOAD', 'ADJUSTMENT']

export function requiresUser(type: StockMovementType): boolean {
  return TYPES_REQUIRING_USER.includes(type)
}

export function requiresNote(type: StockMovementType): boolean {
  return TYPES_REQUIRING_NOTE.includes(type)
}

/**
 * Zod schema of the movement form, mirroring the server rules per type (spec 0307 D-11/D-14):
 * quantity > 0 (counted quantity >= 0 for ADJUSTMENT), a destination site for TRANSFER, a holder
 * for ASSIGN/RETURN, a note for UNLOAD/ADJUSTMENT, a date not after today.
 */
export function buildStockMovementSchema(t: TFunction) {
  return z
    .object({
      type: z.enum(STOCK_MOVEMENT_TYPES),
      product_id: z.number().nullable(),
      operational_site_id: z.number().nullable(),
      to_operational_site_id: z.number().nullable(),
      user_id: z.number().nullable(),
      quantity: z.string(),
      counted_quantity: z.string(),
      moved_on: z.string(),
      note: z.string().max(NOTE_MAX_LENGTH, t('stock.form.noteTooLong')),
    })
    .superRefine((values, ctx) => {
      const issue = (path: keyof typeof values, key: string) =>
        ctx.addIssue({ code: 'custom', path: [path], message: t(`stock.form.${key}`) })

      if (values.product_id === null) {
        issue('product_id', 'productRequired')
      }
      if (values.operational_site_id === null) {
        issue('operational_site_id', 'siteRequired')
      }
      if (values.type === 'TRANSFER') {
        if (values.to_operational_site_id === null) {
          issue('to_operational_site_id', 'toSiteRequired')
        } else if (values.to_operational_site_id === values.operational_site_id) {
          issue('to_operational_site_id', 'toSiteSame')
        }
      }
      if (requiresUser(values.type) && values.user_id === null) {
        issue('user_id', 'userRequired')
      }
      if (values.type === 'ADJUSTMENT') {
        if (!isValidQuantity(values.counted_quantity)) {
          issue('counted_quantity', 'countedInvalid')
        }
      } else if (!isPositiveQuantity(values.quantity)) {
        issue('quantity', 'quantityInvalid')
      }
      if (requiresNote(values.type) && values.note.trim() === '') {
        issue('note', 'noteRequired')
      }
      if (values.moved_on > dayOf(new Date())) {
        issue('moved_on', 'dateInFuture')
      }
    })
}

export type StockMovementFormValues = z.infer<ReturnType<typeof buildStockMovementSchema>>
