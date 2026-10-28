import { z } from 'zod'
import type { TFunction } from 'i18next'

const MIN_PERCENTAGE = 1
const MAX_PERCENTAGE = 100

/**
 * Mirrors the server rules (spec 0321 D-6): integer 1..100 strictly above the
 * current percentage. The date-order rule lives server-side only (it needs the
 * latest entry's date, which the 422 reports on the field).
 */
export function buildProgressSchema(t: TFunction, currentPercentage: number) {
  return z.object({
    percentage: z
      .string()
      .trim()
      .min(1, t('workOrders.progress.percentageRequired'))
      .refine((value) => /^\d+$/.test(value), t('workOrders.progress.percentageInteger'))
      .refine(
        (value) => Number(value) >= MIN_PERCENTAGE && Number(value) <= MAX_PERCENTAGE,
        t('workOrders.progress.percentageRange'),
      )
      .refine(
        (value) => Number(value) > currentPercentage,
        t('workOrders.progress.percentageAboveCurrent', { current: currentPercentage }),
      ),
    recorded_on: z.string().min(1, t('workOrders.progress.dateRequired')),
  })
}

export type ProgressFormValues = z.infer<ReturnType<typeof buildProgressSchema>>
