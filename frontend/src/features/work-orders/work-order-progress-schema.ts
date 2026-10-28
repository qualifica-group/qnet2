import { z } from 'zod'
import type { TFunction } from 'i18next'

const MIN_PERCENTAGE = 1
const MAX_PERCENTAGE = 100

/**
 * Mirrors the server rules (spec 0321 R-2): the typed "% Stato attuale" is an
 * integer 1..100 strictly above the total already reached; the date is required.
 */
export function buildProgressSchema(t: TFunction, currentTotal: number) {
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
      .refine((value) => Number(value) > currentTotal, t('workOrders.progress.totalNotAbove')),
    recorded_on: z.string().min(1, t('workOrders.progress.dateRequired')),
  })
}

export type ProgressFormValues = z.infer<ReturnType<typeof buildProgressSchema>>
