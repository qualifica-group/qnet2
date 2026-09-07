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
import { managerSlotsFromRefs } from '@/lib/utils'
import { createQuote, quoteDetailQueryKey, updateQuote } from '@/features/quotes/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/quotes/quote-form-payload'
import {
  linesToFormValues,
  productTypologyIdsFromLines,
  vatRatePercentsFromLines,
} from '@/features/quotes/quote-line-values'
import { EMPTY_LINE_ROW } from '@/features/quotes/use-quote-lines-field'
import {
  buildCreateQuoteSchema,
  buildUpdateQuoteSchema,
  DEFAULT_MANAGER_SLOTS,
  type QuoteFormValues,
} from '@/features/quotes/quote-schema'
import { useQuoteFormContext } from '@/features/quotes/use-quote-form-context'
import { seedAttributeValues, toAttributeValuesMap } from '@/features/attributes/attribute-values'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type {
  CreateQuotePayload,
  QuoteDetail,
  QuoteFormMode,
  QuoteWorkflowStatusRef,
  UpdateQuotePayload,
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

/**
 * Seeds the shared product -> typology cache from the persisted OFFER rows
 * (spec 0099, D-6: only revenue lines feed the per-typology summary), so the
 * live preview buckets an edit-mode form correctly from the first render —
 * the picker only exposes a typology for products picked in this session.
 */
function initialProductTypologyIds(mode: QuoteFormMode): Record<number, number> {
  if (mode.type !== 'edit') {
    return {}
  }
  return productTypologyIdsFromLines(mode.quote.offer_lines)
}

/**
 * The create form's G.A. slots: one empty card per assignable position, up
 * through `DEFAULT_MANAGER_SLOTS` (mirrors `useOpportunityForm`'s own
 * `defaultManagerSlots`) — a UX default, independent of `MAX_MANAGERS`. D-5's
 * actual inheritance from the Opportunity happens server-side once the form
 * submits this untouched (`buildCreatePayload` omits the key).
 */
function defaultManagerSlots(): (number | null)[] {
  return Array.from({ length: DEFAULT_MANAGER_SLOTS }, () => null)
}

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
  // Spec 0102 D-2/AC-042/043: whether the PERSISTED offer already had at
  // least one line, the fact `buildUpdateQuoteSchema` needs to mirror the
  // server's grandfathering (an offer that was already at zero stays
  // saveable on every other field). Always `false` in create, where the
  // requirement is unconditional (AC-040/041) and this flag plays no part.
  const originalHasOfferLines = mode.type === 'edit' ? mode.quote.offer_lines.length > 0 : false

  // Lo schema BASE: quello che esiste prima che un prodotto sia scelto, senza
  // alcun attributo dinamico. Serve come seme del resolver — il set applicabile
  // dipende da cio' che l'operatore sceglie in QUESTO form (spec 0084 D-5),
  // quindi non puo' essere passato a `useForm` alla costruzione.
  const baseSchema = useMemo(
    () =>
      isEdit
        ? buildUpdateQuoteSchema(t, workflowStatuses, originalStatusId, originalHasOfferLines)
        : buildCreateQuoteSchema(t),
    [isEdit, t, workflowStatuses, originalStatusId, originalHasOfferLines],
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
        manager_slots: managerSlotsFromRefs(quote.managers ?? []),
        company_id: quote.company_id,
        company_site_id: quote.company_site_id,
        operational_site_id: quote.operational_site_id,
        layout_id: quote.layout_id,
        payment_method_id: quote.payment_method_id,
        internal_notes: quote.internal_notes,
        rewards: (quote.rewards ?? []).map((reward) => ({ reward_type_id: reward.reward_type.id })),
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
      // Spec 0087 (D-5): left on the UX default (empty cards); the actual
      // Opportunity inheritance happens server-side once submitted untouched
      // (`buildCreatePayload` then omits the key).
      manager_slots: defaultManagerSlots(),
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
      // Directive 2026-08-31: a new Offerta starts with NO buono — the
      // Opportunita's own assignments are deliberately not copied over, or
      // the same segnalatore would be counted twice in "Segnalatori premiati".
      rewards: [],
      // Directive 2026-09-01: the Offerta tab opens on ONE empty row instead
      // of an empty grid — an offer without lines is the exception, so making
      // the user press "Aggiungi riga" first was pure friction. Costs stay
      // empty: those rows are genuinely optional.
      offer_lines: [EMPTY_LINE_ROW],
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

  // Spec 0099: same shape as the VAT cache above — the product's typology is
  // not on the row (D-5), so the live summary resolves it through this map,
  // seeded from the persisted rows and topped up on every pick.
  const [productTypologyIdByProductId, setProductTypologyIdByProductId] = useState<
    Record<number, number>
  >(() => initialProductTypologyIds(mode))
  const rememberProductTypology = useCallback((productId: number, typologyId: number) => {
    setProductTypologyIdByProductId((previous) =>
      previous[productId] === typologyId ? previous : { ...previous, [productId]: typologyId },
    )
  }, [])
  const productTypologyIdFor = useCallback(
    (productId: number) => productTypologyIdByProductId[productId] ?? null,
    [productTypologyIdByProductId],
  )

  const confirm = useConfirm()

  /** One create/update attempt; `promoteManagers` rides the retry after the D-6 dialog is accepted. */
  const submit = useCallback(
    async (values: QuoteFormValues, promoteManagers: boolean) => {
      if (mode.type === 'edit') {
        const payload: UpdateQuotePayload = buildUpdatePayload(values, mode.quote)
        const saved = await updateQuote(
          mode.quote.id,
          promoteManagers ? { ...payload, promote_managers_to_opportunity: true } : payload,
        )
        queryClient.setQueryData(quoteDetailQueryKey(mode.quote.id), saved)
        toast.success(t('quotes.form.updated'))
        onSuccess(saved)
        return
      }
      const payload: CreateQuotePayload = buildCreatePayload(values)
      const created = await createQuote(
        promoteManagers ? { ...payload, promote_managers_to_opportunity: true } : payload,
      )
      toast.success(t('quotes.form.created'))
      onSuccess(created)
    },
    [mode, queryClient, t, onSuccess],
  )

  const onSubmit = async (values: QuoteFormValues) => {
    setServerError(null)
    const errorFields: Path<QuoteFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      await submit(values, false)
    } catch (error) {
      const membershipMessage = managerSlotsMembershipMessage(error)
      if (membershipMessage === null) {
        if (!applyServerValidationErrors(error, form.setError, errorFields)) {
          setServerError(t('quotes.form.genericError'))
        }
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
        if (!applyServerValidationErrors(retryError, form.setError, errorFields)) {
          setServerError(t('quotes.form.genericError'))
        }
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
    productTypologyIdFor,
    rememberProductTypology,
    // Spec 0084 D-5: risolti QUI perche' lo schema ne dipende; il body li
    // consuma per rendere la sezione, senza risolverli una seconda volta.
    attributeContext,
    attributesLoading,
    hasPickedProduct,
  }
}
