import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import type { Path, Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { toast } from 'sonner'
import { seedAttributeValues } from '@/features/attributes/attribute-values'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  describeAddressIssues,
  describeCardIssues,
  describeContactIssues,
  isPersonalDataCardValid,
} from '@/features/personal-data/personal-data-issues'
import { emptyPersonalDataDraft } from '@/features/personal-data/drafts'
import { emptyProductLineRow } from '@/features/product-lines/types'
import type { AddressDraft, ContactDraft, PersonalDataDraft } from '@/features/personal-data/types'
import { createRequest } from '@/features/request-management/api'
import { buildRequestCreatePayload } from '@/features/request-management/request-create-payload'
import {
  buildRequestCreateSchema,
  type RequestCreateFormValues,
} from '@/features/request-management/request-create-schema'
import { DEFAULT_MANAGER_SLOTS } from '@/features/quotes/quote-schema'
import { EMPTY_LINE_ROW } from '@/features/quotes/use-quote-lines-field'
import { useRequestActorAttributionDefaults } from '@/features/request-management/use-request-actor-defaults'
import { useRequestFormContext } from '@/features/request-management/use-request-form-context'

interface UseRequestCreateFormArgs {
  /** Called after a successful create with the new request's (Offerta/Quote) id. */
  onSuccess: (id: number) => void
}

/** Server-side field names mapped directly onto an RHF field. */
const SCALAR_ERROR_FIELDS: Path<RequestCreateFormValues>[] = [
  'registry_id',
  'source_id',
  'reporter_id',
  'supervisor_id',
  // A per-slot 422 (`manager_slots.<n>`) has no control of its own — the team
  // editor binds the array as a whole — so the block root carries it.
  'manager_slots',
  'operational_site_id',
  // The coherence 422 (user directive 2026-07-31) lands here, on the picker
  // the actor was working in.
  'products_of_interest',
  // The offer rows' own 422s (single-category cap, a category with no
  // business function): per-row paths land on their control, the cross-row
  // ones on the collection.
  'offer_lines',
  // The operative block (user directive 2026-07-31): each maps 1:1 onto its
  // own control, so a server 422 lands inline.
  'next_callback_at',
  'general_notes',
]

/** 422 error groups whose sections live OUTSIDE this form's RHF tree (see below). */
const CLIENT_ERROR_PREFIXES = ['client_identity', 'client_contacts', 'client_address']
const PRODUCT_LINES_ERROR_PREFIXES = ['product_lines']
/** The `rewards`/`rewards.*` D-3 cross-field 422 (reward without a reporter), surfaced as a block banner. */
const REWARDS_ERROR_PREFIXES = ['rewards']

/**
 * Collects every 422 message whose key is one of `prefixes` (exact) or starts
 * with `${prefix}.` (nested/indexed), joined into a single banner string —
 * mirrors `personalDataServerErrorMessage` (`use-registry-form.ts`): the
 * reused anagraphic/product-lines components are not RHF-connected to this
 * form's `client_*`/`product_lines.<index>` paths, so their server errors
 * cannot be routed inline field-by-field and surface as a block banner
 * instead (AC-016 — mapped, not silently dropped).
 */
function collectPrefixedServerErrors(error: unknown, prefixes: string[]): string | null {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) {
    return null
  }
  const errors = error.response.data?.errors as Record<string, string[]> | undefined
  if (!errors) {
    return null
  }
  const messages = Object.entries(errors)
    .filter(([key]) => prefixes.some((prefix) => key === prefix || key.startsWith(`${prefix}.`)))
    .flatMap(([, fieldMessages]) => fieldMessages)
  return messages.length > 0 ? messages.join(' ') : null
}

/**
 * Owns the create form's RHF/Zod wiring (`registry_id`/`product_lines`) plus
 * the buffered client-identity/contacts/address draft (D-2, mirrors
 * `useRegistryForm`'s `profileDraft`): the two anagrafica sources are
 * mutually exclusive, so picking a registry makes the buffer irrelevant
 * without needing to clear it. Submitting builds the frozen
 * `POST /api/request-management` payload (`buildRequestCreatePayload`) and
 * maps the response back onto the caller's `onSuccess(id)` — table refresh /
 * navigation is the caller's concern (mirrors every other `ModuleFormScreen`).
 */
export function useRequestCreateForm({ onSuccess }: UseRequestCreateFormArgs) {
  const { t } = useTranslation()
  const [serverError, setServerError] = useState<string | null>(null)
  // Bumped on every refused save so the buffered identity card marks its own
  // missing fields (it takes no part in this form's submit).
  const [revalidateSignal, setRevalidateSignal] = useState(0)
  const [clientBlockError, setClientBlockError] = useState<string | null>(null)
  const [productLinesError, setProductLinesError] = useState<string | null>(null)
  const [rewardsError, setRewardsError] = useState<string | null>(null)
  const [identityDraft, setIdentityDraft] = useState<PersonalDataDraft>(() => emptyPersonalDataDraft())
  const [contactsDraft, setContactsDraft] = useState<ContactDraft[]>([])
  const [addressDraft, setAddressDraft] = useState<AddressDraft[]>([])

  // The schema is rebuilt from what the operator picks IN THIS FORM (the
  // categories decide the dynamic fields), so it cannot be handed to `useForm`
  // at construction time. The resolver is a stable indirection that always
  // runs the latest one; the seed is the context-less schema, the only one
  // that exists before the first resolution.
  const baseSchema = useMemo(() => buildRequestCreateSchema(t), [t])
  const resolverRef = useRef<Resolver<RequestCreateFormValues>>(zodResolver(baseSchema))

  const form = useForm<RequestCreateFormValues>({
    resolver: (values, context, options) => resolverRef.current(values, context, options),
    defaultValues: {
      registry_id: null,
      // The form opens on ONE empty product-line row (user directive
      // 2026-07-29): at least one is mandatory anyway, so making the user
      // press "Add" first was pure friction.
      product_lines: [emptyProductLineRow()],
      source_id: null,
      reporter_id: null,
      supervisor_id: null,
      // The team opens on `DEFAULT_MANAGER_SLOTS` empty cards, like the
      // Offerte form's own (spec 0097 D-1): untouched, an all-null array never
      // travels, and the server applies its own operator default. The operator
      // slot is seeded from the connected actor right after mount, not here:
      // the abilities that decide whether the block is rendered at all can
      // resolve after the form is built (`useRequestActorAttributionDefaults`).
      manager_slots: Array.from({ length: DEFAULT_MANAGER_SLOTS }, () => null),
      operational_site_id: null,
      products_of_interest: [],
      // "Linee dell'offerta": the form opens on ONE empty row (user directive
      // 2026-09-01, superseding the 2026-08-07 "start with none"), like
      // `product_lines` above — pressing "Add" before the first row was pure
      // friction. An offer-less request stays possible: the operator removes it.
      offer_lines: [EMPTY_LINE_ROW],
      rewards: [],
      next_callback_at: null,
      general_notes: '',
      attribute_values: {},
    },
  })

  // The VAT-percent cache the offer rows' live preview reads (AC-071). Starts
  // empty here — nothing is persisted yet — and fills up as products/rates are
  // picked. Same mechanism as `useQuoteForm`/`useRequestWorkForm`.
  const [vatRatePercentById, setVatRatePercentById] = useState<Record<number, number>>({})
  const rememberVatRatePercent = useCallback((vatRateId: number, percent: number) => {
    setVatRatePercentById((previous) =>
      previous[vatRateId] === percent ? previous : { ...previous, [vatRateId]: percent },
    )
  }, [])
  const vatRatePercentFor = useCallback(
    (vatRateId: number) => vatRatePercentById[vatRateId] ?? null,
    [vatRatePercentById],
  )

  const registryId = useWatch({ control: form.control, name: 'registry_id' })
  const usingExistingRegistry = registryId !== null

  // The create form's live equivalent of what the panel receives already
  // resolved (user directive 2026-08-07): which "Informazioni aggiuntive" the
  // chosen categories carry, and how they are laid out. Watched — not read
  // once — because the categories are being edited in this very form.
  const productLines = useWatch({ control: form.control, name: 'product_lines' })
  const { context, isLoading: isContextLoading } = useRequestFormContext(productLines)

  const schema = useMemo(
    () => buildRequestCreateSchema(t, context.applicable_attributes),
    [t, context.applicable_attributes],
  )

  useEffect(() => {
    resolverRef.current = zodResolver(schema)
  }, [schema])

  // The resolver is swapped as the applicable set changes, so RHF always
  // validates against the fields currently on screen. `setValue` on the whole
  // map (rather than a `reset`) keeps every other field the operator has
  // already filled in — a new category must not wipe the form.
  useEffect(() => {
    form.setValue(
      'attribute_values',
      seedAttributeValues(context.applicable_attributes, form.getValues('attribute_values')),
    )
  }, [context.applicable_attributes, form])

  // The connected actor is the default attribution of a new request (user
  // directive 2026-08-04): Operatore = the actor, Sede operativa = the actor's
  // own Sede, mirrored server-side for the actors who never see the two fields.
  useRequestActorAttributionDefaults(form)

  // The card is mandatory only on the "new client" branch (D-2): block the
  // save until its required-by-type fields validate, mirroring the
  // registries create form's own `profileValid` gate.
  const identityValid = useMemo(
    () => usingExistingRegistry || isPersonalDataCardValid(identityDraft, t),
    [usingExistingRegistry, identityDraft, t],
  )

  /** Refuses the save, naming the offending fields and highlighting them inline. */
  const refuseClientBlock = (messageKey: string, fields: string[]): void => {
    setClientBlockError(t(messageKey, { fields: fields.join(' · ') }))
    setRevalidateSignal((signal) => signal + 1)
  }

  const onSubmit = form.handleSubmit(async (values) => {
    setServerError(null)
    setClientBlockError(null)
    setProductLinesError(null)
    setRewardsError(null)

    if (!usingExistingRegistry) {
      if (!identityValid) {
        refuseClientBlock(
          'requestManagement.form.create.errors.identityIncomplete',
          describeCardIssues(identityDraft, t),
        )
        return
      }
      const addressIssues = describeAddressIssues(addressDraft, t)
      if (addressIssues.length > 0) {
        refuseClientBlock('requestManagement.form.create.errors.addressIncomplete', addressIssues)
        return
      }
      const contactIssues = describeContactIssues(contactsDraft, t)
      if (contactIssues.length > 0) {
        refuseClientBlock('requestManagement.form.create.errors.contactsInvalid', contactIssues)
        return
      }
    }

    const payload = buildRequestCreatePayload({
      registryId: values.registry_id,
      identity: identityDraft,
      contacts: contactsDraft,
      address: addressDraft[0] ?? null,
      productLines: values.product_lines,
      sourceId: values.source_id,
      reporterId: values.reporter_id,
      supervisorId: values.supervisor_id,
      managerSlots: values.manager_slots,
      operationalSiteId: values.operational_site_id,
      productsOfInterest: values.products_of_interest,
      offerLines: values.offer_lines,
      rewards: values.rewards,
      nextCallbackAt: values.next_callback_at,
      generalNotes: values.general_notes,
      attributeValues: values.attribute_values,
      attributeCodes: context.applicable_attributes.map((attribute) => attribute.code),
    })

    try {
      const created = await createRequest(payload)
      toast.success(t('requestManagement.form.create.success'))
      onSuccess(created.id)
    } catch (error) {
      // `attribute_values.<code>` is appended per applicable attribute at
      // submit time: the set is dynamic, so it cannot live in the constant.
      const mappedScalar = applyServerValidationErrors(error, form.setError, [
        ...SCALAR_ERROR_FIELDS,
        ...context.applicable_attributes.map(
          (attribute) => `attribute_values.${attribute.code}` as Path<RequestCreateFormValues>,
        ),
      ])
      const clientMessage = collectPrefixedServerErrors(error, CLIENT_ERROR_PREFIXES)
      const productLinesMessage = collectPrefixedServerErrors(error, PRODUCT_LINES_ERROR_PREFIXES)
      const rewardsMessage = collectPrefixedServerErrors(error, REWARDS_ERROR_PREFIXES)
      setClientBlockError(clientMessage)
      setProductLinesError(productLinesMessage)
      setRewardsError(rewardsMessage)
      if (!mappedScalar && !clientMessage && !productLinesMessage && !rewardsMessage) {
        setServerError(t('requestManagement.form.create.errors.generic'))
      }
    }
  })

  return {
    form,
    onSubmit,
    isSubmitting: form.formState.isSubmitting,
    usingExistingRegistry,
    identityDraft,
    setIdentityDraft,
    revalidateSignal,
    contactsDraft,
    setContactsDraft,
    addressDraft,
    setAddressDraft,
    serverError,
    clientBlockError,
    productLinesError,
    rewardsError,
    /** The server-resolved attributes/layout the "Informazioni aggiuntive" section renders from. */
    context,
    isContextLoading,
    vatRatePercentFor,
    rememberVatRatePercent,
  }
}
