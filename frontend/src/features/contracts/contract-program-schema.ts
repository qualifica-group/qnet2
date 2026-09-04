import { z } from 'zod'
import type { TFunction } from 'i18next'

/** Mirrors `WorkOrder.title`'s `string(191)` column limit (`workOrders.form.titleMax`). */
const TITLE_MAX_LENGTH = 191

/**
 * "Programma" (spec 0095 D-11, extended by spec 0096 D-5): the dialog carries
 * exactly the fields a commessa cannot be left without — `title`, `type`,
 * `start_date` and at least one Responsabile — plus at least one selected
 * offer line (AC-061). Partecipanti and the dynamic "Informazioni aggiuntive"
 * are deliberately absent: both are filled in later from the work order's own
 * form (decisione utente 2026-09-04, che revoca AC-019 di spec 0098).
 *
 * Messages reuse the work-order form's own strings (`workOrders.form.*`)
 * rather than duplicating them — same fields, same rules, same wording.
 */
export function buildContractProgramSchema(t: TFunction) {
  return z.object({
    title: z
      .string()
      .min(1, t('workOrders.form.titleRequired'))
      .max(TITLE_MAX_LENGTH, t('workOrders.form.titleMax')),
    type: z.enum(['processing', 'project'], { message: t('workOrders.form.typeRequired') }),
    start_date: z.string().min(1, t('workOrders.form.startDateRequired')),
    supervisor_ids: z.array(z.number()).min(1, t('workOrders.form.supervisorsRequired')),
    quote_line_ids: z.array(z.number()).min(1, t('contracts.actions.programDialog.linesRequired')),
  })
}

export type ContractProgramFormValues = z.infer<ReturnType<typeof buildContractProgramSchema>>

export function contractProgramDefaultValues(): ContractProgramFormValues {
  return {
    title: '',
    type: 'processing',
    start_date: '',
    supervisor_ids: [],
    quote_line_ids: [],
  }
}
