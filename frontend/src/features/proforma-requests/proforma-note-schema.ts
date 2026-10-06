import { z } from 'zod'
import type { TFunction } from 'i18next'

/** Mirrors the server rule (`note` required, max 5000). */
export const PROFORMA_NOTE_MAX_LENGTH = 5000

/** Zod schema of the single-field form shared by the "€" modal and the edit form. */
export function buildProformaNoteSchema(t: TFunction) {
  return z.object({
    note: z
      .string()
      .trim()
      .min(1, t('proformaRequests.form.noteRequired'))
      .max(PROFORMA_NOTE_MAX_LENGTH, t('proformaRequests.form.noteTooLong', { max: PROFORMA_NOTE_MAX_LENGTH })),
  })
}

export type ProformaNoteFormValues = z.infer<ReturnType<typeof buildProformaNoteSchema>>
