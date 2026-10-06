import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import type { Path, Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { toast } from 'sonner'
import { useConfirm } from '@/components/confirm-dialog-context'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { seedAttributeValues } from '@/features/attributes/attribute-values'
import { createQuote, quoteDetailQueryKey, updateQuote } from '@/features/quotes/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/quotes/quote-form-payload'
import { createDefaults, editDefaults } from '@/features/quotes/quote-form-defaults'
import { buildCreateQuoteSchema, buildUpdateQuoteSchema, type QuoteFormValues } from '@/features/quotes/quote-schema'
import { useQuoteFormContext, type PersistedQuoteContext } from '@/features/quotes/use-quote-form-context'
import { useQuoteLineCaches } from '@/features/quotes/use-quote-line-caches'
import type { QuoteDetail, QuoteFormMode, QuoteWorkflowStatusRef } from '@/features/quotes/types'

/** Hoisted so the schema memo keeps a stable dependency in create mode (no set resolved yet). */
const EMPTY_STATUSES: QuoteWorkflowStatusRef[] = []

/** Server-side field names mapped onto the form for 422 handling (mirrors `opportunities`/`projects`). */
const SERVER_ERROR_FIELDS = [
  'code',
  'title',
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
  'layout_id',
  'payment_method_id',
  'internal_notes',
  'rewards',
  'offer_lines',
  'cost_lines',
] as const

/**
 * Spec 0087 (D-6/AC-004): the create/update 422 is the "not a G.A. of the
 * Opportunity yet" refusal when the error bag carries a `manager_slots` (or
 * `manager_slots.<n>`) key — the client never rejects it earlier (MAX is the
 * only client-side rule), so a 422 landing there past that point is this
 * violation. Returns the server's own message (AC-004 names the user) or
 * `null` when the failure is unrelated.
 */
function managerSlotsMembershipMessage(error: unknown): string | null {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) {
    return null
  }
  const errors = error.response.data?.errors as Record<string, string[]> | undefined
  const key = Object.keys(errors ?? {}).find(
    (field) => field === 'manager_slots' || field.startsWith('manager_slots.'),
  )
  return key ? (errors as Record<string, string[]>)[key][0] : null
}

interface UseQuoteFormArgs {
  mode: QuoteFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (quote: QuoteDetail) => void
  /** Create-only: sequential code suggestion prefilled into the `code` default (D-13/AC-082). */
  initialCode?: string
}

/**
 * Owns every non-render concern of the quote create form (`QuoteFormBody`)
 * and of the in-place detail (`useQuoteInlineEdit`, edit mode, spec 0197):
 * RHF/Zod wiring, default values, the live Attribute set, the shared line
 * caches the live summary reads, server 422 mapping and the create/update
 * submit (with the spec 0087 D-6 promotion retry).
 */
export function useQuoteForm({ mode, onSuccess, initialCode }: UseQuoteFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const confirm = useConfirm()
  const [serverError, setServerError] = useState<string | null>(null)
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

  // Spec 0084 D-5: l'innesco e' la scelta del PRODOTTO sulle righe OFFERTA.
  const offerLines = useWatch({ control: form.control, name: 'offer_lines' })
  const pickedProductIds = useMemo(
    () => (offerLines ?? []).map((line) => line.product_id).filter((id): id is number => id !== null),
    [offerLines],
  )
  const { context: attributeContext, isLoading: attributesLoading, hasPickedProduct } =
    useQuoteFormContext(pickedProductIds, persistedContext)

  // Lo schema vero, ricostruito ogni volta che il set applicabile cambia, cosi'
  // RHF valida SEMPRE contro i campi realmente a schermo (obbligatorieta'
  // inclusa). Senza questo swap la refine sui `is_required` resterebbe inerte.
  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateQuoteSchema(
            t,
            workflowStatuses,
            originalStatusId,
            originalHasOfferLines,
            attributeContext.applicable_attributes,
          )
        : buildCreateQuoteSchema(t, attributeContext.applicable_attributes),
    [isEdit, t, workflowStatuses, originalStatusId, originalHasOfferLines, attributeContext.applicable_attributes],
  )

  useEffect(() => {
    resolverRef.current = zodResolver(schema)
  }, [schema])

  // Il set applicabile arriva DOPO la costruzione del form: senza seminare una
  // chiave per ogni `code` risolto, l'oggetto Zod appena swappato rifiuterebbe
  // quelle mancanti e `handleSubmit` abortirebbe in silenzio, con errori su
  // campi mai toccati. `setValue` sulla mappa intera, non `reset`: gli altri
  // campi gia' compilati restano. Stesso pattern di `useOpportunityForm`.
  useEffect(() => {
    form.setValue(
      'attribute_values',
      seedAttributeValues(attributeContext.applicable_attributes, form.getValues('attribute_values')),
    )
  }, [attributeContext.applicable_attributes, form])

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
      const created = await createQuote({ ...buildCreatePayload(values), ...promotion })
      toast.success(t('quotes.form.created'))
      onSuccess(created)
    },
    [mode, queryClient, form, t, onSuccess],
  )

  const reportFailure = (error: unknown) => {
    if (!applyServerValidationErrors(error, form.setError, [...SERVER_ERROR_FIELDS] as Path<QuoteFormValues>[])) {
      setServerError(t('quotes.form.genericError'))
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
    hasPickedProduct,
  }
}

/** Everything the quote's sections read: the form, the line caches and the resolved Attributes. */
export type QuoteFormState = ReturnType<typeof useQuoteForm>
