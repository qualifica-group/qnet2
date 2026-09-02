import type { FieldErrors } from 'react-hook-form'
import type { TFunction } from 'i18next'
import type { RequestWorkFormValues } from '@/features/request-management/request-work-schema'

/**
 * Names the fields that refused the work panel's submit, for the summary shown
 * next to the (sticky, top) save button. Extracted from `useRequestWorkForm`
 * to keep that hook at its single responsibility — the RHF/Zod wiring.
 */

/**
 * The blocks whose editor does NOT read the parent form's errors:
 * `PersonalDataCardForm`, `ContactsManager` and `AddressCreateField` take a
 * buffered value plus `onChange` and nothing else, so their message exists
 * only in the form state. For these the summary carries the message itself,
 * not just the section name — otherwise it would point at a group whose fields
 * all look filled in (a legacy tax code inconsistent with the surname is the
 * common case) with no way to tell which one is wrong.
 */
const BUFFERED_BLOCKS = new Set(['client_identity', 'client_contacts', 'client_address'])

/**
 * Human label of each root field of the form. Since spec 0097 the team is one
 * field, `manager_slots` (a section of its own since rev-2): naming a single
 * slot here would be wrong (any of them can be the one that refused the
 * submit) and the per-slot G.A. denominations are already on screen, on the
 * cards themselves.
 */
function buildFieldLabels(t: TFunction): Record<string, string> {
  return {
    next_callback_at: t('requestManagement.workPanel.callback.label'),
    client_identity: t('requestManagement.workPanel.client.identityGroup'),
    client_contacts: t('requestManagement.workPanel.client.contactsGroup'),
    client_address: t('requestManagement.workPanel.client.addressGroup'),
    product_lines: t('requestManagement.workPanel.productLines.fieldLabel'),
    offer_lines: t('quotes.form.offerTab.fieldLabel'),
    rewards: t('requestManagement.workPanel.attribution.rewards.fieldLabel'),
    source_id: t('requestManagement.workPanel.attribution.source'),
    reporter_id: t('requestManagement.workPanel.attribution.reporter'),
    supervisor_id: t('requestManagement.workPanel.team.supervisor'),
    manager_slots: t('requestManagement.workPanel.team.managers'),
    operational_site_id: t('requestManagement.workPanel.attribution.operationalSite'),
  }
}

/**
 * Every distinct message under one root error, whatever its depth: a block's
 * issues sit at `client_contacts.<index>.<field>` or `client_identity.<field>`,
 * and RHF nests them accordingly.
 */
function collectMessages(error: unknown): string[] {
  if (!error || typeof error !== 'object') {
    return []
  }

  const node = error as { message?: unknown }
  if (typeof node.message === 'string' && node.message !== '') {
    return [node.message]
  }

  const nested = Object.values(error as Record<string, unknown>).flatMap(collectMessages)

  return [...new Set(nested)]
}

/** Names the blocking fields. */
export function describeInvalidFields(errors: FieldErrors<RequestWorkFormValues>, t: TFunction): string[] {
  const labels = buildFieldLabels(t)

  return Object.entries(errors).flatMap(([key, error]) => {
    const label = labels[key] ?? key

    if (!BUFFERED_BLOCKS.has(key)) {
      return [label]
    }

    const messages = collectMessages(error)

    return [messages.length > 0 ? `${label}: ${messages.join(' ')}` : label]
  })
}
