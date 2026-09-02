import { z } from 'zod'
import type { TFunction } from 'i18next'

/** Mirrors `WorkOrder.title`'s `string(191)` column limit (`workOrders.form.titleMax`). */
const TITLE_MAX_LENGTH = 191

/**
 * "Programma" (spec 0095 D-11): `title` and `type` are required (`WorkOrder`
 * columns are NOT NULL with no default), plus at least one selected offer
 * line (AC-061). Messages reuse the work-order form's own strings
 * (`workOrders.form.*`) rather than duplicating them — same fields, same
 * rules, same wording.
 */
export function buildContractProgramSchema(t: TFunction) {
  return z.object({
    title: z
      .string()
      .min(1, t('workOrders.form.titleRequired'))
      .max(TITLE_MAX_LENGTH, t('workOrders.form.titleMax')),
    type: z.enum(['processing', 'project'], { message: t('workOrders.form.typeRequired') }),
    quote_line_ids: z.array(z.number()).min(1, t('contracts.actions.programDialog.linesRequired')),
  })
}

export type ContractProgramFormValues = z.infer<ReturnType<typeof buildContractProgramSchema>>

export function contractProgramDefaultValues(): ContractProgramFormValues {
  return { title: '', type: 'processing', quote_line_ids: [] }
}
