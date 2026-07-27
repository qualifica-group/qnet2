import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import { opportunityDetailQueryKey } from '@/features/opportunities/api'
import { addressToDraft } from '@/features/personal-data/drafts'
import type { ContactDraft, PersonalDataDraft } from '@/features/personal-data/types'
import { updateRequestWork } from '@/features/request-management/api'
import { requestManagementKeys } from '@/features/request-management/query-keys'
import { buildRequestWorkPayload } from '@/features/request-management/request-work-payload'
import {
  buildRequestWorkSchema,
  type RequestWorkFormValues,
} from '@/features/request-management/request-work-schema'
import type {
  ApplicableAttribute,
  RequestClientIdentity,
  RequestContact,
  RequestWorkPanelWithPermissions,
} from '@/features/request-management/types'

/**
 * Maps the client's card identity to the draft `PersonalDataCardForm` reads.
 * Its `contacts`/`addresses` stay empty on purpose: in this panel those two
 * live in their own RHF fields (`client_contacts`/`client_address`), the card
 * form neither renders nor emits them.
 */
function toIdentityDraft(identity: RequestClientIdentity): PersonalDataDraft {
  return {
    id: identity.id,
    type: identity.type,
    first_name: identity.first_name,
    last_name: identity.last_name,
    company_name: identity.company_name,
    tax_code: identity.tax_code,
    vat_number: identity.vat_number,
    sdi_code: identity.sdi_code,
    birth_date: identity.birth_date,
    birth_city_id: identity.birth_city_id,
    birth_city: identity.birth_city,
    // Mirrors `cardToDraft`: an individual always carries a gender (default
    // male, backfilling a legacy null), a company carries none.
    gender: identity.gender ?? (identity.type === 'company' ? null : 'male'),
    contacts: [],
    addresses: [],
  }
}

/** Maps one panel contact projection to the buffered draft shape the managers read. */
function toContactDraft(contact: RequestContact): ContactDraft {
  return {
    _key: `contact-${contact.id}`,
    id: contact.id,
    type: contact.type,
    value: contact.value,
    label: contact.label,
    is_primary: contact.is_primary,
  }
}

/** Seeds the dynamic `attribute_values` RHF slice: every applicable code, `null` when unset. */
function seedAttributeValues(
  attributes: ApplicableAttribute[],
  values: Record<string, unknown>,
): Record<string, CustomFieldValue> {
  const seeded: Record<string, CustomFieldValue> = {}
  for (const attribute of attributes) {
    seeded[attribute.code] = (values[attribute.code] as CustomFieldValue | undefined) ?? null
  }
  return seeded
}

function buildDefaultValues(panel: RequestWorkPanelWithPermissions): RequestWorkFormValues {
  return {
    opportunity_workflow_status_id: panel.workflow_status?.id ?? null,
    next_callback_at: panel.next_callback_at ?? null,
    // The note (spec 0054 D-5) is transient: never loaded from the panel,
    // always reset to blank after a save.
    note: '',
    client_identity: panel.client_identity ? toIdentityDraft(panel.client_identity) : null,
    client_contacts: panel.client_contacts.items.map(toContactDraft),
    // 0-or-1 array: the shape `AddressCreateField` reads, empty when the
    // client has no address yet.
    client_address: panel.client_address ? [addressToDraft(panel.client_address)] : [],
    attribute_values: seedAttributeValues(panel.applicable_attributes, panel.attribute_values),
    products_of_interest: panel.products_of_interest.map((product) => product.id),
    rewards: (panel.rewards ?? []).map((reward) => ({ reward_type_id: reward.reward_type.id })),
    source_id: panel.source_id,
    reporter_id: panel.reporter_id,
    operator_id: panel.operator_id,
    operational_site_id: panel.operational_site_id,
  }
}

/**
 * Owns the RHF/Zod wiring of the work panel's editable surface (spec 0049
 * AC-061/062): dynamic schema/defaults derived from the loaded panel, sparse
 * PATCH submit (`buildRequestWorkPayload`), 422 mapped onto the working-state
 * field and every `attribute_values.<code>` path (accessible triad via
 * `MetaField`/`FormMessage`, frontend.md §10).
 */
export function useRequestWorkForm(panel: RequestWorkPanelWithPermissions) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const schema = useMemo(
    () => buildRequestWorkSchema(panel.applicable_attributes, panel.workflow_statuses, panel.workflow_status?.id ?? null, t),
    [panel.applicable_attributes, panel.workflow_statuses, panel.workflow_status, t],
  )

  const defaultValues = useMemo(() => buildDefaultValues(panel), [panel])

  const form = useForm<RequestWorkFormValues>({ resolver: zodResolver(schema), defaultValues })

  const errorFields: Path<RequestWorkFormValues>[] = [
    'opportunity_workflow_status_id' as Path<RequestWorkFormValues>,
    'next_callback_at' as Path<RequestWorkFormValues>,
    'note' as Path<RequestWorkFormValues>,
    // The client block is submitted as a whole: a per-row 422
    // (`client_contacts.0.value`) has no matching control here, so the block
    // root carries the message.
    'client_contacts' as Path<RequestWorkFormValues>,
    'client_address' as Path<RequestWorkFormValues>,
    'products_of_interest' as Path<RequestWorkFormValues>,
    'rewards' as Path<RequestWorkFormValues>,
    'source_id' as Path<RequestWorkFormValues>,
    'reporter_id' as Path<RequestWorkFormValues>,
    'operator_id' as Path<RequestWorkFormValues>,
    'operational_site_id' as Path<RequestWorkFormValues>,
    ...panel.applicable_attributes.map(
      (attribute) => `attribute_values.${attribute.code}` as Path<RequestWorkFormValues>,
    ),
  ]

  const onSubmit = form.handleSubmit(async (values) => {
    setServerError(null)
    const payload = buildRequestWorkPayload(values, panel)
    if (Object.keys(payload).length === 0) {
      return
    }
    try {
      const updated = await updateRequestWork(panel.id, payload)
      queryClient.setQueryData(requestManagementKeys.panel(panel.id), updated)
      queryClient.invalidateQueries({ queryKey: opportunityDetailQueryKey(panel.id) })
      toast.success(t('requestManagement.workPanel.saved', { defaultValue: 'Working data saved.' }))
      form.reset(buildDefaultValues(updated))
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(
          t('requestManagement.workPanel.genericError', {
            defaultValue: 'Something went wrong. Please try again.',
          }),
        )
      }
    }
  })

  return { form, onSubmit, serverError, isSubmitting: form.formState.isSubmitting }
}
