import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import type { Path, Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createQuote, quoteDetailQueryKey, updateQuote } from '@/features/quotes/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/quotes/quote-form-payload'
import { linesToFormValues, vatRatePercentsFromLines } from '@/features/quotes/quote-line-values'
import {
  buildCreateQuoteSchema,
  buildUpdateQuoteSchema,
  type QuoteFormValues,
} from '@/features/quotes/quote-schema'
import { useQuoteFormContext } from '@/features/quotes/use-quote-form-context'
import { seedAttributeValues, toAttributeValuesMap } from '@/features/attributes/attribute-values'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type {
  QuoteDetail,
  QuoteFormMode,
  QuoteWorkflowStatusRef,
} from '@/features/quotes/types'

/** Hoisted so the schema memo keeps a stable dependency in create mode (no set resolved yet). */
const EMPTY_STATUSES: QuoteWorkflowStatusRef[] = []

/** Hoisted: un literal inline creerebbe un nuovo riferimento a ogni render. */
const EMPTY_ATTRIBUTE_VALUES: Record<string, CustomFieldValue> = {}

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
  'company_id',
  'company_site_id',
  'operational_site_id',
  'layout_id',
  'payment_method_id',
  'internal_notes',
  'offer_lines',
  'cost_lines',
] as const

/**
 * Seeds the shared VAT-percent cache from every persisted line's own
 * hydrated rate, so the live preview (AC-071) is byte-exact from the first
 * render in edit mode — the `vat-rates/for-select` picker itself never
 * exposes a percentage (see `quote-product-select.tsx`'s comment).
 */
function initialVatRatePercents(mode: QuoteFormMode): Record<number, number> {
  if (mode.type !== 'edit') {
    return {}
  }
  return vatRatePercentsFromLines([...mode.quote.offer_lines, ...mode.quote.cost_lines])
}

interface UseQuoteFormArgs {
  mode: QuoteFormMode
  /** Called after a successful create/update so the caller can navigate to the detail page. */
  onSuccess: (quote: QuoteDetail) => void
  /** Create-only: sequential code suggestion prefilled into the `code` default (D-13/AC-082). */
  initialCode?: string
}

/**
 * Owns the RHF/Zod wiring, the shared VAT-percent cache used by the live
 * summary preview, and the create/update submit of `QuoteFormBody`. Mirrors
 * `useOpportunityForm`/`useProjectForm`, folded into a single hook since
 * quotes has no equivalent of the lead-conversion split.
 */
export function useQuoteForm({ mode, onSuccess, initialCode }: UseQuoteFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)
  const isEdit = mode.type === 'edit'

  // Spec 0083: the update schema needs the resolved set and the status the
  // quote currently holds to decide whether the transition note is mandatory
  // (AC-023/AC-026). Both are `null`/empty in create, where no transition
  // happens: the backend assigns the `open` row itself (AC-020).
  const workflowStatuses = mode.type === 'edit' ? (mode.quote.quote_workflow_statuses ?? EMPTY_STATUSES) : EMPTY_STATUSES
  const originalStatusId = mode.type === 'edit' ? mode.quote.quote_workflow_status_id : null

  // Lo schema BASE: quello che esiste prima che un prodotto sia scelto, senza
  // alcun attributo dinamico. Serve come seme del resolver — il set applicabile
  // dipende da cio' che l'operatore sceglie in QUESTO form (spec 0084 D-5),
  // quindi non puo' essere passato a `useForm` alla costruzione.
  const baseSchema = useMemo(
    () =>
      isEdit
        ? buildUpdateQuoteSchema(t, workflowStatuses, originalStatusId)
        : buildCreateQuoteSchema(t),
    [isEdit, t, workflowStatuses, originalStatusId],
  )

  const defaultValues = useMemo<QuoteFormValues>(() => {
    if (mode.type === 'edit') {
      const { quote } = mode
      return {
        code: quote.code,
        title: quote.title,
        opportunity_id: quote.opportunity_id,
        quote_workflow_status_id: quote.quote_workflow_status_id,
        note: null,
        attribute_values: toAttributeValuesMap(quote.attribute_values),
        commercial_id: quote.commercial_id,
        reporter_id: quote.reporter_id,
        supervisor_id: quote.supervisor_id,
        company_id: quote.company_id,
        company_site_id: quote.company_site_id,
        operational_site_id: quote.operational_site_id,
        layout_id: quote.layout_id,
        payment_method_id: quote.payment_method_id,
        internal_notes: quote.internal_notes,
        offer_lines: linesToFormValues(quote.offer_lines),
        cost_lines: linesToFormValues(quote.cost_lines),
      }
    }
    // Spec 0067 AC-050/051: a numeric `params.opportunity_id` seeds the
    // otherwise-empty create form (the panel "Crea Offerta" flow); the field
    // is then locked read-only by `QuoteFormBody`'s `forceDisabled`.
    const forcedOpportunityId =
      typeof mode.params?.opportunity_id === 'number' ? mode.params.opportunity_id : null
    return {
      code: initialCode ?? '',
      title: '',
      opportunity_id: forcedOpportunityId,
      quote_workflow_status_id: null,
      note: null,
      attribute_values: EMPTY_ATTRIBUTE_VALUES,
      commercial_id: null,
      reporter_id: null,
      supervisor_id: null,
      company_id: null,
      company_site_id: null,
      operational_site_id: null,
      // Precompiled with the module's active default layout by
      // `QuoteLayoutSection` (spec 0070 D-3/AC-310), not here: resolving it
      // needs a network round trip, out of scope for a synchronous default.
      layout_id: null,
      // No server-side default to mirror (unlike `layout_id`): a new quote
      // starts with no payment method until the user picks one.
      payment_method_id: null,
      internal_notes: null,
      offer_lines: [],
      cost_lines: [],
    }
  }, [mode, initialCode])

  // Indirezione stabile: `useForm` riceve un resolver che non cambia mai
  // identita', ma che esegue sempre l'ultimo schema costruito. Stesso pattern
  // gia' collaudato in `useOpportunityForm`/`useRequestCreateForm`.
  const resolverRef = useRef<Resolver<QuoteFormValues>>(zodResolver(baseSchema))

  const form = useForm<QuoteFormValues>({
    resolver: (values, context, options) => resolverRef.current(values, context, options),
    defaultValues,
  })

  // Spec 0084 D-5: l'innesco e' la scelta del PRODOTTO sulle righe OFFERTA.
  const offerLines = useWatch({ control: form.control, name: 'offer_lines' })
  const pickedProductIds = useMemo(
    () => (offerLines ?? []).map((line) => line.product_id).filter((id): id is number => id !== null),
    [offerLines],
  )
  const { context: attributeContext, isLoading: attributesLoading, hasPickedProduct } =
    useQuoteFormContext(pickedProductIds)

  // Lo schema vero, ricostruito ogni volta che il set applicabile cambia, cosi'
  // RHF valida SEMPRE contro i campi realmente a schermo (obbligatorieta'
  // inclusa). Senza questo swap la refine sui `is_required` resterebbe inerte.
  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateQuoteSchema(t, workflowStatuses, originalStatusId, attributeContext.applicable_attributes)
        : buildCreateQuoteSchema(t, attributeContext.applicable_attributes),
    [isEdit, t, workflowStatuses, originalStatusId, attributeContext.applicable_attributes],
  )

  useEffect(() => {
    resolverRef.current = zodResolver(schema)
  }, [schema])

  // Il set applicabile arriva DOPO la costruzione del form: senza seminare una
  // chiave per ogni `code` risolto, l'oggetto Zod appena swappato rifiuterebbe
  // quelle mancanti (un'offerta salvata prima che l'Attributo esistesse non le
  // ha) e `handleSubmit` abortirebbe in silenzio, con errori su campi mai
  // toccati. `setValue` sulla mappa intera, non `reset`: gli altri campi gia'
  // compilati restano. Stesso pattern di `useOpportunityForm`.
  useEffect(() => {
    form.setValue(
      'attribute_values',
      seedAttributeValues(attributeContext.applicable_attributes, form.getValues('attribute_values')),
    )
  }, [attributeContext.applicable_attributes, form])

  const [vatRatePercentById, setVatRatePercentById] = useState<Record<number, number>>(() =>
    initialVatRatePercents(mode),
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

  const onSubmit = async (values: QuoteFormValues) => {
    setServerError(null)
    const errorFields: Path<QuoteFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        const saved = await updateQuote(mode.quote.id, buildUpdatePayload(values, mode.quote))
        queryClient.setQueryData(quoteDetailQueryKey(mode.quote.id), saved)
        toast.success(t('quotes.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createQuote(buildCreatePayload(values))
      toast.success(t('quotes.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('quotes.form.genericError'))
      }
    }
  }

  return {
    form,
    isEdit,
    serverError,
    onSubmit,
    vatRatePercentFor,
    rememberVatRatePercent,
    // Spec 0084 D-5: risolti QUI perche' lo schema ne dipende; il body li
    // consuma per rendere la sezione, senza risolverli una seconda volta.
    attributeContext,
    attributesLoading,
    hasPickedProduct,
  }
}
