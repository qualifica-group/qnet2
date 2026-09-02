/**
 * Names the fields that block the save of a buffered anagraphic tree.
 *
 * The card, the contacts and the address of a create flow are NOT RHF fields:
 * they are owner-agnostic buffers the parent form carries (ADR 0012), so a
 * refused submit could only ever report "complete the required fields" — a
 * message that points at nothing. These describers re-run the very schemas the
 * inline editors use and turn each failure into `"<field>: <message>"`, so the
 * banner says WHICH field to fill. Single source for every module that embeds
 * the section (registries, referents, users, company sites, request creation,
 * self-service profile).
 */
import type { TFunction } from 'i18next'
import { buildContactSchema } from '@/features/personal-data/contact-schema'
import { buildPersonalDataSchema } from '@/features/personal-data/personal-data-schema'
import type {
  AddressDraft,
  ContactDraft,
  PersonalDataDraft,
} from '@/features/personal-data/types'

/** Which block of the buffered tree refused the save, so the owner can reveal it. */
export type BlockedSection = 'card' | 'contacts' | 'addresses'

/** The card fields validated by the shared schema, projected from a buffered draft. */
function cardValues(draft: PersonalDataDraft) {
  return {
    type: draft.type,
    first_name: draft.first_name ?? undefined,
    last_name: draft.last_name ?? undefined,
    company_name: draft.company_name ?? undefined,
    tax_code: draft.tax_code ?? undefined,
    vat_number: draft.vat_number ?? undefined,
    sdi_code: draft.sdi_code ?? undefined,
    birth_date: draft.birth_date ?? undefined,
    birth_city_id: draft.birth_city_id ?? null,
    residence_city_id: draft.residence_city_id ?? null,
    gender: draft.gender ?? undefined,
  }
}

/** `"<field label>: <message>"`, the shape every describer returns. */
function describe(label: string, message: string): string {
  return `${label}: ${message}`
}

/** Same list, without the repeats a multi-issue field or a repeated row produces. */
function distinct(entries: string[]): string[] {
  return [...new Set(entries)]
}

/**
 * Whether the buffered card satisfies its per-type required fields. Validates
 * the SAME values `describeCardIssues` reports on, so the save gate and the
 * banner can never disagree with the messages the card shows inline.
 */
export function isPersonalDataCardValid(draft: PersonalDataDraft, t: TFunction): boolean {
  return buildPersonalDataSchema(t).safeParse(cardValues(draft)).success
}

/** One entry per failing card field, named by its label (`personalData.fieldLabels.*`). */
export function describeCardIssues(draft: PersonalDataDraft, t: TFunction): string[] {
  const result = buildPersonalDataSchema(t).safeParse(cardValues(draft))

  if (result.success) {
    return []
  }

  return distinct(
    result.error.issues.map((issue) =>
      describe(t(`personalData.fieldLabels.${String(issue.path[0])}`), issue.message),
    ),
  )
}

/**
 * The quick-create address rule: an address nobody started is fine, a started
 * one needs its street and its city. Mirrors `isCreateAddressValid`, which is
 * expressed in terms of this.
 */
export function describeAddressIssues(addresses: AddressDraft[], t: TFunction): string[] {
  return distinct(
    addresses.flatMap((address) => {
      const issues: string[] = []

      if (!address.line1) {
        issues.push(
          describe(t('personalData.addresses.line1'), t('personalData.addresses.line1Required')),
        )
      }
      if (address.city_id == null) {
        issues.push(describe(t('geo.city'), t('personalData.addresses.cityRequired')))
      }

      return issues
    }),
  )
}

/** i18n key of a quick contact's label, so its issue names the field the user typed in. */
const CONTACT_TYPE_LABEL_KEYS: Record<string, string> = {
  email: 'personalData.contacts.quickEmail',
  phone: 'personalData.contacts.quickPhone',
  pec: 'personalData.contacts.quickPec',
  fax: 'personalData.contacts.quickFax',
}

/**
 * One entry per invalid buffered contact. A type without a dedicated quick
 * field (added through the dialog) falls back to its own enum value: the
 * translated labels for those live in the server config, out of reach here.
 */
export function describeContactIssues(contacts: ContactDraft[], t: TFunction): string[] {
  const schema = buildContactSchema(t)

  return distinct(
    contacts.flatMap((contact) => {
      const result = schema.safeParse({
        type: contact.type,
        value: contact.value,
        label: contact.label ?? '',
        is_primary: contact.is_primary,
      })

      if (result.success) {
        return []
      }

      const labelKey = CONTACT_TYPE_LABEL_KEYS[contact.type]
      const label = labelKey ? t(labelKey) : contact.type

      return result.error.issues.map((issue) => describe(label, issue.message))
    }),
  )
}
