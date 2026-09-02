/**
 * Pure helpers of `PersonalDataCardForm`, kept out of the component file so it
 * stays within the engineering size limits (`.claude/rules/engineering.md` §6):
 * the buffer's no-op compare and the focus target used when a refused save
 * asks the card to show what is missing.
 */
import type { UseFormReturn } from 'react-hook-form'
import type { PersonalDataFormValues } from '@/features/personal-data/personal-data-schema'
import type { PersonalDataDraft } from '@/features/personal-data/types'

/**
 * The text inputs the focus may land on, in reading order. The remaining card
 * controls (the type toggle, the gender select, the two comune pickers) carry
 * no RHF-registered element to focus, and none of them can be the missing
 * required field: the type and the gender always hold a value.
 */
const FOCUSABLE_FIELDS = [
  'company_name',
  'first_name',
  'last_name',
  'tax_code',
  'vat_number',
  'sdi_code',
  'birth_date',
] as const

/** Focuses (and scrolls to) the first invalid input of the card, if any. */
export function focusFirstInvalid(form: UseFormReturn<PersonalDataFormValues>): void {
  const target = FOCUSABLE_FIELDS.find((name) => form.getFieldState(name).invalid)

  if (target) {
    form.setFocus(target)
  }
}

/** True when two drafts carry identical card fields (children ignored). */
export function sameCardFields(a: PersonalDataDraft, b: PersonalDataDraft): boolean {
  return (
    a.type === b.type &&
    a.first_name === b.first_name &&
    a.last_name === b.last_name &&
    a.company_name === b.company_name &&
    a.tax_code === b.tax_code &&
    a.vat_number === b.vat_number &&
    a.sdi_code === b.sdi_code &&
    a.birth_date === b.birth_date &&
    a.birth_city_id === b.birth_city_id &&
    a.residence_city_id === b.residence_city_id &&
    a.gender === b.gender
  )
}
