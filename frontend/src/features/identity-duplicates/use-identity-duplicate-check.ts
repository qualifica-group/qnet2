import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import {
  checkIdentityDuplicates,
  identityDuplicateCheckQueryKey,
  type IdentityDuplicateContact,
  type IdentityDuplicateContactType,
  type IdentityDuplicateMatch,
} from '@/features/identity-duplicates/duplicate-check-api'
import type { ContactDraft, PersonalDataDraft } from '@/features/personal-data/types'

/** Contact types the check matches on — the rest of the type enum (pec, fax, website…) is out of scope (spec 0037). */
const MATCHED_CONTACT_TYPES = new Set<string>(['email', 'phone', 'mobile'])

interface UseIdentityDuplicateCheckArgs {
  /** The check only runs on CREATE: an edit form would match the very card it is editing. */
  enabled: boolean
  /** The buffered anagraphic draft the form is editing (`use-referent-form.ts` / `use-registry-form.ts`). */
  profileDraft: PersonalDataDraft
}

interface UseIdentityDuplicateCheckResult {
  matches: IdentityDuplicateMatch[]
}

/** Non-empty, trimmed email/phone/mobile contacts from the draft, shaped for the wire. */
function relevantContacts(contacts: ContactDraft[]): IdentityDuplicateContact[] {
  return contacts
    .filter((contact) => MATCHED_CONTACT_TYPES.has(contact.type) && contact.value.trim().length > 0)
    .map((contact) => ({
      type: contact.type as IdentityDuplicateContactType,
      value: contact.value.trim(),
    }))
}

/**
 * Debounced, non-blocking duplicate check (spec 0037, extended by the user
 * directive 2026-09-09): watches the buffered tax_code, vat_number and
 * email/phone/mobile contacts of a CREATE form and asks the backend which
 * users/anagrafiche/referenti already carry them. Never fires while every
 * criterion is empty (AC-008) — that, like `enabled`, is baked into the query,
 * not just hidden in the UI.
 */
export function useIdentityDuplicateCheck({
  enabled,
  profileDraft,
}: UseIdentityDuplicateCheckArgs): UseIdentityDuplicateCheckResult {
  const taxCode = profileDraft.tax_code?.trim() ?? ''
  const vatNumber = profileDraft.vat_number?.trim() ?? ''
  const contacts = useMemo(
    () => relevantContacts(profileDraft.contacts),
    [profileDraft.contacts],
  )

  const debouncedTaxCode = useDebouncedValue(taxCode)
  const debouncedVatNumber = useDebouncedValue(vatNumber)
  const debouncedContacts = useDebouncedValue(contacts)

  const hasCriteria =
    debouncedTaxCode.length > 0 || debouncedVatNumber.length > 0 || debouncedContacts.length > 0

  const query = useQuery({
    queryKey: identityDuplicateCheckQueryKey(debouncedTaxCode, debouncedVatNumber, debouncedContacts),
    queryFn: () =>
      checkIdentityDuplicates({
        tax_code: debouncedTaxCode || undefined,
        vat_number: debouncedVatNumber || undefined,
        contacts: debouncedContacts.length > 0 ? debouncedContacts : undefined,
      }),
    enabled: enabled && hasCriteria,
  })

  return { matches: enabled ? (query.data?.matches ?? []) : [] }
}
