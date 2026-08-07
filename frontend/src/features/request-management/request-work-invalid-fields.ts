import type { FieldErrors } from 'react-hook-form'
import type { TFunction } from 'i18next'
import type { RequestWorkFormValues } from '@/features/request-management/request-work-schema'
import { OPERATOR_MANAGER_LABEL_POSITION } from '@/features/request-management/types'
import type { ManagerLabels } from '@/features/request-management/types'

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
 * Human label of each root field of the form. `operator_id` mirrors the
 * attribution section's own resolution (spec 0080): the request's resolved
 * G.A. level-2 label when the category defines one, otherwise today's string.
 */
function buildFieldLabels(t: TFunction, managerLabels: ManagerLabels | undefined): Record<string, string> {
  return {
    next_callback_at: t('requestManagement.workPanel.callback.label'),
    client_identity: t('requestManagement.workPanel.client.identityGroup'),
    client_contacts: t('requestManagement.workPanel.client.contactsGroup'),
    client_address: t('requestManagement.workPanel.client.addressGroup'),
    product_lines: t('requestManagement.workPanel.productLines.fieldLabel'),
    rewards: t('requestManagement.workPanel.attribution.rewards.fieldLabel'),
    source_id: t('requestManagement.workPanel.attribution.source'),
    reporter_id: t('requestManagement.workPanel.attribution.reporter'),
    operator_id: managerLabels?.[OPERATOR_MANAGER_LABEL_POSITION] ?? t('requestManagement.workPanel.attribution.operator'),
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
export function describeInvalidFields(
  errors: FieldErrors<RequestWorkFormValues>,
  managerLabels: ManagerLabels | undefined,
  t: TFunction,
): string[] {
  const labels = buildFieldLabels(t, managerLabels)

  return Object.entries(errors).flatMap(([key, error]) => {
    const label = labels[key] ?? key

    if (!BUFFERED_BLOCKS.has(key)) {
      return [label]
    }

    const messages = collectMessages(error)

    return [messages.length > 0 ? `${label}: ${messages.join(' ')}` : label]
  })
}
