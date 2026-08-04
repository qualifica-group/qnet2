import { useEffect, useMemo, useRef, useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import type { Path, Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { areCreateContactsValid, isCreateAddressValid } from '@/features/personal-data/create-validation'
import { emptyPersonalDataDraft } from '@/features/personal-data/drafts'
import { emptyProductLineRow } from '@/features/product-lines/types'
import { buildPersonalDataSchema } from '@/features/personal-data/personal-data-schema'
import type { AddressDraft, ContactDraft, PersonalDataDraft } from '@/features/personal-data/types'
import { createRequest } from '@/features/request-management/api'
import { seedAttributeValues } from '@/features/request-management/request-work-payload'
import { buildRequestCreatePayload } from '@/features/request-management/request-create-payload'
import {
  buildRequestCreateSchema,
  type RequestCreateFormValues,
} from '@/features/request-management/request-create-schema'
import { useRequestActorAttributionDefaults } from '@/features/request-management/use-request-actor-defaults'
import { useRequestFormContext } from '@/features/request-management/use-request-form-context'

interface UseRequestCreateFormArgs {
  /** Called after a successful create with the new request's (Opportunity) id. */
  onSuccess: (id: number) => void
}

/** Server-side field names mapped directly onto an RHF field. */
const SCALAR_ERROR_FIELDS: Path<RequestCreateFormValues>[] = [
  'registry_id',
  'source_id',
  'reporter_id',
  'operator_id',
  'operational_site_id',
  // The coherence 422 (user directive 2026-07-31) lands here, on the picker
  // the actor was working in.
  'products_of_interest',
  // The operative block (user directive 2026-07-31): each maps 1:1 onto its
  // own control, so a server 422 lands inline. `attribute_values.<code>` is
  // appended per applicable attribute at submit time (the set is dynamic).
  'opportunity_workflow_status_id',
  'note',
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
  const [clientBlockError, setClientBlockError] = useState<string | null>(null)
  const [productLinesError, setProductLinesError] = useState<string | null>(null)
  const [rewardsError, setRewardsError] = useState<string | null>(null)
  const [identityDraft, setIdentityDraft] = useState<PersonalDataDraft>(() => emptyPersonalDataDraft())
  const [contactsDraft, setContactsDraft] = useState<ContactDraft[]>([])
  const [addressDraft, setAddressDraft] = useState<AddressDraft[]>([])

  // The schema is rebuilt from what the operator picks IN THIS FORM (the
  // categories decide the dynamic fields, the resolved statuses decide the
  // select), so it cannot be handed to `useForm` at construction time. The
  // resolver is a stable indirection that always runs the latest one; the
  // seed is the context-less schema, the only one that exists before the
  // first resolution.
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
      // Seeded from the connected actor right after mount, not here: the
      // abilities that decide whether these two are rendered at all can resolve
      // after the form is built (`useRequestActorAttributionDefaults`).
      operator_id: null,
      operational_site_id: null,
      products_of_interest: [],
      rewards: [],
      opportunity_workflow_status_id: null,
      note: '',
      next_callback_at: null,
      general_notes: '',
      attribute_values: {},
    },
  })

  const registryId = useWatch({ control: form.control, name: 'registry_id' })
  const usingExistingRegistry = registryId !== null

  // The connected actor is the default attribution of a new request (user
  // directive 2026-08-04): Operatore = the actor, Sede operativa = the actor's
  // own Sede, mirrored server-side for the actors who never see the two fields.
  useRequestActorAttributionDefaults(form)

  // The create form's live equivalent of what the panel receives already
  // resolved (user directive 2026-07-31): which working statuses may be
  // picked, which dynamic fields the chosen categories carry, how they are laid
  // out. Watched — not read once — because the categories are being edited in
  // the very form these blocks belong to.
  const sourceId = useWatch({ control: form.control, name: 'source_id' })
  const productLines = useWatch({ control: form.control, name: 'product_lines' })
  const { context, isLoading: isContextLoading } = useRequestFormContext(sourceId, productLines)

  const schema = useMemo(
    () => buildRequestCreateSchema(t, context.applicable_attributes, context.workflow_statuses),
    [t, context.applicable_attributes, context.workflow_statuses],
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

  // The first status of the resolved set is the default (user directive
  // 2026-08-04): as soon as the criteria enable the select it carries a value
  // instead of an empty placeholder. The set arrives ordered by `sort_order`
  // from `statusesFor()`, so "first" is the configured first.
  //
  // The same effect covers the selection that LEFT the set (the categories
  // changed under it): it falls back to the new set's default rather than to
  // null, since submitting an out-of-set id would 422.
  useEffect(() => {
    const statuses = context.workflow_statuses
    const selected = form.getValues('opportunity_workflow_status_id')

    if (selected !== null && statuses.some((status) => status.id === selected)) {
      return
    }

    if (statuses.length === 0) {
      if (selected !== null) {
        form.setValue('opportunity_workflow_status_id', null)
      }

      return
    }

    form.setValue('opportunity_workflow_status_id', statuses[0].id)
  }, [context.workflow_statuses, form])

  // The card is mandatory only on the "new client" branch (D-2): block the
  // save until its required-by-type fields validate, mirroring the
  // registries create form's own `profileValid` gate.
  const identityValid = useMemo(
    () =>
      usingExistingRegistry ||
      buildPersonalDataSchema(t).safeParse({
        type: identityDraft.type,
        first_name: identityDraft.first_name ?? undefined,
        last_name: identityDraft.last_name ?? undefined,
        company_name: identityDraft.company_name ?? undefined,
        tax_code: identityDraft.tax_code ?? undefined,
        vat_number: identityDraft.vat_number ?? undefined,
        birth_date: identityDraft.birth_date ?? undefined,
      }).success,
    [usingExistingRegistry, identityDraft, t],
  )

  const onSubmit = form.handleSubmit(async (values) => {
    setServerError(null)
    setClientBlockError(null)
    setProductLinesError(null)
    setRewardsError(null)

    if (!usingExistingRegistry) {
      if (!identityValid) {
        setClientBlockError(t('requestManagement.form.create.errors.identityIncomplete'))
        return
      }
      if (!isCreateAddressValid(addressDraft)) {
        setClientBlockError(t('requestManagement.form.create.errors.addressIncomplete'))
        return
      }
      if (!areCreateContactsValid(contactsDraft, t)) {
        setClientBlockError(t('requestManagement.form.create.errors.contactsInvalid'))
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
      operatorId: values.operator_id,
      operationalSiteId: values.operational_site_id,
      productsOfInterest: values.products_of_interest,
      rewards: values.rewards,
      workflowStatusId: values.opportunity_workflow_status_id,
      statusNote: values.note,
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
    /** The server-resolved statuses/attributes/layout the operative sections render from. */
    context,
    isContextLoading,
    usingExistingRegistry,
    identityDraft,
    setIdentityDraft,
    contactsDraft,
    setContactsDraft,
    addressDraft,
    setAddressDraft,
    serverError,
    clientBlockError,
    productLinesError,
    rewardsError,
  }
}
