import { z } from 'zod'
import type { TFunction } from 'i18next'

/** Backend column limits (spec 0020 schema `company_site_banks`). */
const NAME_MAX_LENGTH = 191
const IBAN_MAX_LENGTH = 50
const NOTES_MAX_LENGTH = 191

/**
 * Zod schema for a single bank row (create/edit dialog inside
 * `banks-manager.tsx`), built as a factory for localized messages. Mirrors
 * the backend nested rules (`banks.*.name` required, `banks.*.iban` nullable,
 * `banks.*.notes` nullable). The IBAN carries no format constraint beyond its
 * max length — it is a free-text field, not restricted to SEPA IBANs.
 */
export function buildBankSchema(t: TFunction) {
  return z.object({
    name: z
      .string()
      .min(1, t('companySites.form.banks.nameRequired'))
      .max(NAME_MAX_LENGTH, t('companySites.form.banks.nameMax')),
    iban: z.string().max(IBAN_MAX_LENGTH, t('companySites.form.banks.ibanMax')),
    notes: z.string().max(NOTES_MAX_LENGTH, t('companySites.form.banks.notesMax')),
    // The site's preferred bank; the manager enforces at most one across the
    // list (single-primary, mirroring contacts/addresses).
    is_primary: z.boolean(),
  })
}

export type BankFormValues = z.infer<ReturnType<typeof buildBankSchema>>
