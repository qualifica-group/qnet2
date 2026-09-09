import { useCallback, useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { managerSlotsFromRefs, padManagerSlots } from '@/lib/utils'
import { seedAttributeValues, toAttributeValuesMap } from '@/features/attributes/attribute-values'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { opportunityDetailQueryKey } from '@/features/opportunities/api'
import { vatRatePercentsFromLines } from '@/features/quotes/quote-line-values'
import { DEFAULT_MANAGER_SLOTS } from '@/features/quotes/quote-schema'
import { addressToDraft } from '@/features/personal-data/drafts'
import type { ContactDraft, PersonalDataDraft } from '@/features/personal-data/types'
import { updateRequestWork } from '@/features/request-management/api'
import { requestManagementKeys } from '@/features/request-management/query-keys'
import { describeInvalidFields } from '@/features/request-management/request-work-invalid-fields'
import { buildRequestWorkPayload, openingOfferLines, toProductLineRows } from '@/features/request-management/request-work-payload'
import {
  buildRequestWorkSchema,
  type RequestWorkFormValues,
} from '@/features/request-management/request-work-schema'
import type {
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
    residence_city_id: identity.residence_city_id,
    residence_city: identity.residence_city,
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

function buildDefaultValues(panel: RequestWorkPanelWithPermissions): RequestWorkFormValues {
  return {
    next_callback_at: panel.next_callback_at ?? null,
    // "Note generali" (direttiva utente 2026-09-09): '' when the request
    // carries none — the field is always rendered, precisely so an empty note
    // can be typed in.
    general_notes: panel.general_notes ?? '',
    client_identity: panel.client_identity ? toIdentityDraft(panel.client_identity) : null,
    client_contacts: panel.client_contacts.items.map(toContactDraft),
    // 0-or-1 array: the shape `AddressCreateField` reads, empty when the
    // client has no address yet.
    client_address: panel.client_address ? [addressToDraft(panel.client_address)] : [],
    product_lines: toProductLineRows(panel.product_lines),
    // "Linee dell'offerta" (user directive 2026-08-07): hydrated by the SAME
    // mapper the Offerte form uses, minus the provvigioni block — this
    // channel neither renders nor sends it (the endpoint prohibits it and the
    // server preserves what is persisted). A request with no persisted line
    // opens on one empty row (user directive 2026-09-09).
    offer_lines: openingOfferLines(panel.offer_lines),
    // Seeded over the APPLICABLE codes, never the raw stored map: the Zod
    // object is built from those codes and a missing key aborts the submit
    // silently (see `seedAttributeValues`).
    attribute_values: seedAttributeValues(
      panel.applicable_attributes,
      toAttributeValuesMap(panel.attribute_values),
    ),
    quote_workflow_status_id: panel.quote_workflow_status_id,
    // Never prefilled: the note belongs to the transition being made now, not
    // to the request.
    note: null,
    rewards: (panel.rewards ?? []).map((reward) => ({ reward_type_id: reward.reward_type.id })),
    source_id: panel.source_id,
    reporter_id: panel.reporter_id,
    supervisor_id: panel.supervisor_id,
    // Spec 0097 D-1: the team, rebuilt from the pivot and padded out to the
    // module-wide default so an operator always sees a full set of editable
    // cards — a request whose Offerta has no G.A. yet would otherwise open on
    // the bare "add slot" button, with no OPERATOR_MANAGER_POSITION to link
    // the Sede to. Padding never reaches the wire: the payload's positional
    // diff reads trailing empty slots as unchanged.
    manager_slots: padManagerSlots(managerSlotsFromRefs(panel.managers ?? []), DEFAULT_MANAGER_SLOTS),
    operational_site_id: panel.operational_site_id,
  }
}

/**
 * Owns the RHF/Zod wiring of the work panel's editable surface (spec 0049
 * AC-061/062): dynamic schema/defaults derived from the loaded panel, sparse
 * PATCH submit (`buildRequestWorkPayload`), 422 mapped onto each field
 * (accessible triad via `MetaField`/`FormMessage`, frontend.md §10).
 */
export function useRequestWorkForm(panel: RequestWorkPanelWithPermissions) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [submitError, setSubmitError] = useState<string | null>(null)

  const schema = useMemo(
    () =>
      buildRequestWorkSchema(
        {
          product_lines: toProductLineRows(panel.product_lines),
          // The SAME baseline `buildRequestWorkPayload` diffs against, so the
          // schema validates exactly the map that is going to travel.
          attribute_values: seedAttributeValues(
            panel.applicable_attributes,
            toAttributeValuesMap(panel.attribute_values),
          ),
          quote_workflow_status_id: panel.quote_workflow_status_id,
          client_identity: panel.client_identity,
          client_contacts: panel.client_contacts.items,
          client_address: panel.client_address,
        },
        panel.applicable_attributes,
        panel.quote_workflow_statuses,
        t,
      ),
    [
      panel.product_lines,
      panel.applicable_attributes,
      panel.attribute_values,
      panel.quote_workflow_status_id,
      panel.quote_workflow_statuses,
      panel.client_identity,
      panel.client_contacts,
      panel.client_address,
      t,
    ],
  )

  const defaultValues = useMemo(() => buildDefaultValues(panel), [panel])

  const form = useForm<RequestWorkFormValues>({ resolver: zodResolver(schema), defaultValues })

  // The VAT-percent cache the offer rows' live preview reads (AC-071), seeded
  // from the persisted lines' own hydrated rate: the `vat-rates/for-select`
  // picker never exposes a percentage. Same mechanism as `useQuoteForm`.
  const [vatRatePercentById, setVatRatePercentById] = useState<Record<number, number>>(() =>
    vatRatePercentsFromLines(panel.offer_lines),
  )
  const rememberVatRatePercent = useCallback((vatRateId: number, percent: number) => {
    setVatRatePercentById((previous) =>
      previous[vatRateId] === percent ? previous : { ...previous, [vatRateId]: percent },
    )
  }, [])
  const vatRatePercentFor = useCallback(
    (vatRateId: number) => vatRatePercentById[vatRateId] ?? null,
    [vatRatePercentById],
  )

  const errorFields: Path<RequestWorkFormValues>[] = [
    'next_callback_at' as Path<RequestWorkFormValues>,
    // The client block is submitted as a whole: a per-row 422
    // (`client_contacts.0.value`) has no matching control here, so the block
    // root carries the message.
    'client_contacts' as Path<RequestWorkFormValues>,
    'client_address' as Path<RequestWorkFormValues>,
    // The collection is submitted as a whole: a per-row 422
    // (`product_lines.0.business_function_id`) has no control of its own here,
    // so the block root carries the message.
    'product_lines' as Path<RequestWorkFormValues>,
    // A per-row 422 (`offer_lines.0.quantity`) DOES have a matching control:
    // the row editor binds each field by index, so the message lands on the
    // row it belongs to; the collection root carries the cross-row ones
    // (single-category cap, coverage).
    'offer_lines' as Path<RequestWorkFormValues>,
    'rewards' as Path<RequestWorkFormValues>,
    'source_id' as Path<RequestWorkFormValues>,
    'reporter_id' as Path<RequestWorkFormValues>,
    'supervisor_id' as Path<RequestWorkFormValues>,
    // A per-slot 422 (`manager_slots.<n>`) has no control of its own — the
    // editor binds the array as a whole — so the block root carries it.
    'manager_slots' as Path<RequestWorkFormValues>,
    'operational_site_id' as Path<RequestWorkFormValues>,
    // A per-code 422 (`attribute_values.<code>`) DOES have a matching control:
    // the layout renderer binds each field to that exact path, so the message
    // lands on the field it belongs to.
    'attribute_values' as Path<RequestWorkFormValues>,
    'quote_workflow_status_id' as Path<RequestWorkFormValues>,
    'note' as Path<RequestWorkFormValues>,
  ]

  const onSubmit = form.handleSubmit(
    async (values) => {
      setSubmitError(null)
      const payload = buildRequestWorkPayload(values, panel)
      if (Object.keys(payload).length === 0) {
        return
      }
      try {
        const updated = await updateRequestWork(panel.id, payload)
        queryClient.setQueryData(requestManagementKeys.panel(panel.id), updated)
        // The panel's own id is now the Offerta id (spec 0086): the opportunity
        // detail cache is keyed on the underlying Opportunity's own id.
        queryClient.invalidateQueries({ queryKey: opportunityDetailQueryKey(panel.opportunity_id) })
        toast.success(t('requestManagement.workPanel.saved', { defaultValue: 'Working data saved.' }))
        form.reset(buildDefaultValues(updated))
      } catch (error) {
        if (!applyServerValidationErrors(error, form.setError, errorFields)) {
          setSubmitError(
            t('requestManagement.workPanel.genericError', {
              defaultValue: 'Something went wrong. Please try again.',
            }),
          )
        }
      }
    },
    (errors) => {
      setSubmitError(
        t('requestManagement.workPanel.validation.summary', {
          fields: describeInvalidFields(errors, t).join(', '),
        }),
      )
    },
  )

  return {
    form,
    onSubmit,
    submitError,
    isSubmitting: form.formState.isSubmitting,
    vatRatePercentFor,
    rememberVatRatePercent,
  }
}
