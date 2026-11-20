import { useCallback, useEffect, useMemo, useRef } from 'react'
import { useFormSubmitError } from '@/features/auth/use-form-submit-error'
import { useForm, useWatch } from 'react-hook-form'
import type { Path, Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { useConfirm } from '@/components/confirm-dialog-context'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { seedAttributeValues, toAttributeValuesMap } from '@/features/attributes/attribute-values'
import { createQuote, quoteDetailQueryKey, updateQuote } from '@/features/quotes/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/quotes/quote-form-payload'
import { createDefaults, editDefaults } from '@/features/quotes/quote-form-defaults'
import { buildCreateQuoteSchema, buildUpdateQuoteSchema, type QuoteFormValues } from '@/features/quotes/quote-schema'
import { useQuoteFormContext, type PersistedQuoteContext } from '@/features/quotes/use-quote-form-context'
import { managerSlotsMembershipMessage, withStoredFallback } from '@/features/quotes/use-quote-form-helpers'
import { useQuoteLineCaches } from '@/features/quotes/use-quote-line-caches'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { QuoteDetail, QuoteFormMode, QuoteWorkflowStatusRef } from '@/features/quotes/types'

/** Hoisted so the schema memo keeps a stable dependency in create mode (no set resolved yet). */
const EMPTY_STATUSES: QuoteWorkflowStatusRef[] = []
const EMPTY_ATTRIBUTE_VALUES: Record<string, CustomFieldValue> = {}

/** Server-side field names mapped onto the form for 422 handling (mirrors `opportunities`/`projects`). */
const SERVER_ERROR_FIELDS = [
  'code',
  'title',
  'quote_date',
  'follow_up_date',
  'opportunity_id',
  'quote_workflow_status_id',
  'note',
  'commercial_id',
  'reporter_id',
  'supervisor_id',
  'manager_slots',
  'company_id',
  'company_site_id',
  'operational_site_id',
  'source_id',
  'layout_id',
  'payment_method_id',
  'internal_notes',
  'rewards',
  'offer_lines',
  'cost_lines',
] as const

interface UseQuoteFormArgs {
  mode: QuoteFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (quote: QuoteDetail) => void
  /** Create-only: sequential code suggestion prefilled into the `code` default (D-13/AC-082). */
  initialCode?: string
  /**
   * Spec 0396: set by the new opportunity + offer procedure, where the
   * opportunity does not exist yet. The Opportunita' is then not required, and
   * `categoryIds` (the unsaved opportunity's product lines) feed the live
   * Attributes in its place.
   */
  withNewOpportunity?: { categoryIds: number[] }
}

/**
 * Owns every non-render concern of the quote create form (`QuoteFormBody`)
 * and of the in-place detail (`useQuoteInlineEdit`, edit mode, spec 0197):
 * RHF/Zod wiring, default values, the live Attribute set, the shared line
 * caches the live summary reads, server 422 mapping and the create/update
 * submit (with the spec 0087 D-6 promotion retry).
 */
export function useQuoteForm({ mode, onSuccess, initialCode, withNewOpportunity }: UseQuoteFormArgs) {
  const { t, i18n } = useTranslation()
  const queryClient = useQueryClient()
  const confirm = useConfirm()
  const [serverError, setServerError] = useFormSubmitError()
  const isEdit = mode.type === 'edit'
  const original = mode.type === 'edit' ? mode.quote : null

  // Spec 0083: the update schema needs the resolved set and the status the
  // quote currently holds to decide whether the transition note is mandatory
  // (AC-023/AC-026). Both are `null`/empty in create, where no transition
  // happens: the backend assigns the `open` row itself (AC-020).
  const workflowStatuses = original?.quote_workflow_statuses ?? EMPTY_STATUSES
  const originalStatusId = original?.quote_workflow_status_id ?? null
  // Spec 0102 D-2/AC-042/043: whether the PERSISTED offer already had at
  // least one line, the fact `buildUpdateQuoteSchema` needs to mirror the
  // server's grandfathering (an offer that was already at zero stays
  // saveable on every other field). Always `false` in create, where the
  // requirement is unconditional (AC-040/041) and this flag plays no part.
  const originalHasOfferLines = original !== null && original.offer_lines.length > 0

  const defaultValues = useMemo<QuoteFormValues>(
    () => (mode.type === 'edit' ? editDefaults(mode.quote) : createDefaults(mode, initialCode)),
    [mode, initialCode],
  )

  // Indirezione stabile: `useForm` riceve un resolver che non cambia mai
  // identita', ma che esegue sempre l'ultimo schema costruito dal set di
  // attributi risolto live (spec 0084 D-5).
  const resolverRef = useRef<Resolver<QuoteFormValues>>(
    zodResolver(
      isEdit
        ? buildUpdateQuoteSchema(t, workflowStatuses, originalStatusId, originalHasOfferLines)
        : buildCreateQuoteSchema(t),
    ),
  )

  // Edit mode IS the quote detail (spec 0197): the persisted record can change
  // under the form, so `values` re-syncs it while `keepDirtyValues` preserves
  // the row still being edited. Every explicit `reset` that means to DROP an
  // edit passes `keepDirtyValues: false` (RHF merges `resetOptions` into every
  // reset, explicit options winning).
  const form = useForm<QuoteFormValues>({
    resolver: (values, context, options) => resolverRef.current(values, context, options),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: isEdit ? { keepDirtyValues: true } : undefined,
  })

  const persistedContext = useMemo<PersistedQuoteContext | undefined>(
    () =>
      mode.type === 'edit'
        ? {
            productIds: mode.quote.offer_lines.map((line) => line.product_id),
            context: {
              applicable_attributes: mode.quote.applicable_attributes,
              attribute_layout: mode.quote.attribute_layout,
            },
          }
        : undefined,
    [mode],
  )

  const persistedAttributeValues = useMemo(
    () => (mode.type === 'edit' ? toAttributeValuesMap(mode.quote.attribute_values) : EMPTY_ATTRIBUTE_VALUES),
    [mode],
  )

  // Spec 0084 D-5: gli inneschi sono l'Opportunita' (le sue righe prodotto) e
  // la scelta del PRODOTTO sulle righe OFFERTA.
  const opportunityId = useWatch({ control: form.control, name: 'opportunity_id' })
  const offerLines = useWatch({ control: form.control, name: 'offer_lines' })
  const pickedProductIds = useMemo(
    () => (offerLines ?? []).map((line) => line.product_id).filter((id): id is number => id !== null),
    [offerLines],
  )
  const { context: attributeContext, isLoading: attributesLoading } = useQuoteFormContext(
    opportunityId ?? null,
    pickedProductIds,
    persistedContext,
    withNewOpportunity?.categoryIds,
  )

  // Lo schema vero, ricostruito ogni volta che il set applicabile cambia, cosi'
  // RHF valida SEMPRE contro i campi realmente a schermo (obbligatorieta'
  // inclusa). Senza questo swap la refine sui `is_required` resterebbe inerte.
  // The layout the screen renders decides which sections (hence which required
  // attributes) are hidden (spec 0288 D-4): the detail's view layout in edit,
  // the live context layout in create.
  const renderedLayout =
    mode.type === 'edit' ? (mode.quote.attribute_view_layout ?? null) : attributeContext.attribute_layout

  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateQuoteSchema(
            t,
            workflowStatuses,
            originalStatusId,
            originalHasOfferLines,
            attributeContext.applicable_attributes,
            renderedLayout,
          )
        : buildCreateQuoteSchema(
            t,
            attributeContext.applicable_attributes,
            renderedLayout,
            withNewOpportunity !== undefined,
          ),
    [
      isEdit,
      withNewOpportunity,
      t,
      workflowStatuses,
      originalStatusId,
      originalHasOfferLines,
      attributeContext.applicable_attributes,
      renderedLayout,
    ],
  )

  useEffect(() => {
    resolverRef.current = zodResolver(schema)
  }, [schema])

  // Il set applicabile arriva DOPO la costruzione del form: senza seminare una
  // chiave per ogni `code` risolto, l'oggetto Zod appena swappato rifiuterebbe
  // quelle mancanti e `handleSubmit` abortirebbe in silenzio, con errori su
  // campi mai toccati. `setValue` sulla mappa intera, non `reset`: gli altri
  // campi gia' compilati restano. Stesso pattern di `useOpportunityForm`.
  // Un codice appena diventato applicabile parte dal valore SALVATO, se c'e'
  // (segnalazione 2026-11-11): la mappa del form porta solo i codici del set
  // precedente, e seminarlo vuoto mostrerebbe vuoto un valore esistente.
  useEffect(() => {
    form.setValue(
      'attribute_values',
      seedAttributeValues(
        attributeContext.applicable_attributes,
        withStoredFallback(form.getValues('attribute_values'), persistedAttributeValues),
      ),
    )
  }, [attributeContext.applicable_attributes, persistedAttributeValues, form])

  const lineCaches = useQuoteLineCaches(original)

  /** One create/update attempt; `promoteManagers` rides the retry after the D-6 dialog is accepted. */
  const submit = useCallback(
    async (values: QuoteFormValues, promoteManagers: boolean) => {
      const promotion = promoteManagers ? { promote_managers_to_opportunity: true } : {}
      if (mode.type === 'edit') {
        // Step 1 (edit): PATCH what changed and refresh the cached detail.
        const saved = await updateQuote(mode.quote.id, { ...buildUpdatePayload(values, mode.quote), ...promotion })
        queryClient.setQueryData(quoteDetailQueryKey(mode.quote.id), saved)
        // Step 2 (edit): the detail stays mounted on the saved record, clean.
        form.reset(editDefaults(saved), { keepDirtyValues: false })
        toast.success(t('quotes.form.updated'))
        onSuccess(saved)
        return
      }
      const created = await createQuote({ ...buildCreatePayload(values, initialCode), ...promotion })
      toast.success(t('quotes.form.created'))
      onSuccess(created)
    },
    [mode, queryClient, form, t, onSuccess, initialCode],
  )

  const reportFailure = (error: unknown) => {
    if (!applyServerValidationErrors(error, form.setError, [...SERVER_ERROR_FIELDS] as Path<QuoteFormValues>[])) {
      setServerError(apiErrorMessage(error, t('quotes.form.genericError'), i18n))
    }
  }

  const onSubmit = async (values: QuoteFormValues) => {
    setServerError(null)
    try {
      await submit(values, false)
    } catch (error) {
      const membershipMessage = managerSlotsMembershipMessage(error)
      if (membershipMessage === null) {
        reportFailure(error)
        return
      }
      // Spec 0087 (D-6): a picked G.A. is not yet a G.A. of the linked
      // Opportunity. Ask before widening the Opportunity's own team; a
      // decline cancels the save outright (user directive 2026-08-31) — no
      // generic error, the dialog already explained why.
      const promote = await confirm({
        tone: 'warning',
        title: t('quotes.form.managersPromoteDialog.title'),
        description: membershipMessage,
        confirmLabel: t('quotes.form.managersPromoteDialog.confirm'),
        cancelLabel: t('common.cancel'),
      })
      if (!promote) {
        return
      }
      try {
        await submit(values, true)
      } catch (retryError) {
        reportFailure(retryError)
      }
    }
  }

  return {
    form,
    isEdit,
    serverError,
    clearServerError: () => setServerError(null),
    onSubmit,
    ...lineCaches,
    // Spec 0084 D-5: risolti QUI perche' lo schema ne dipende; le sezioni li
    // consumano per rendere i campi, senza risolverli una seconda volta.
    attributeContext,
    attributesLoading,
  }
}

/** Everything the quote's sections read: the form, the line caches and the resolved Attributes. */
export type QuoteFormState = ReturnType<typeof useQuoteForm>
