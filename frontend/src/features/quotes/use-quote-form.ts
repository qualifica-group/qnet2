import { useCallback, useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createQuote, quoteDetailQueryKey, updateQuote } from '@/features/quotes/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/quotes/quote-form-payload'
import {
  buildCreateQuoteSchema,
  buildUpdateQuoteSchema,
  type QuoteFormValues,
  type QuoteLineFormValues,
} from '@/features/quotes/quote-schema'
import type { QuoteDetail, QuoteFormMode, QuoteLine } from '@/features/quotes/types'

/** Server-side field names mapped onto the form for 422 handling (mirrors `opportunities`/`projects`). */
const SERVER_ERROR_FIELDS = [
  'code',
  'title',
  'opportunity_id',
  'quote_status_id',
  'commercial_id',
  'reporter_id',
  'supervisor_id',
  'company_id',
  'company_site_id',
  'operational_site_id',
  'layout_id',
  'internal_notes',
  'offer_lines',
  'cost_lines',
] as const

/** Rebuilds a persisted line into the row-editor's nullable-per-field form shape, ordered by `sort_order` (AC-038). */
function linesToFormValues(lines: QuoteLine[]): QuoteLineFormValues[] {
  return lines
    .slice()
    .sort((a, b) => a.sort_order - b.sort_order)
    .map((line) => ({
      id: line.id,
      product_id: line.product_id,
      quantity: Number(line.quantity),
      unit_price: Number(line.unit_price),
      vat_rate_id: line.vat_rate_id,
      commissions: (line.commissions ?? []).map((commission) => ({
        ...commission,
        value: Number(commission.value),
      })),
    }))
}

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
  const entries: Record<number, number> = {}
  for (const line of [...mode.quote.offer_lines, ...mode.quote.cost_lines]) {
    if (line.vat_rate) {
      entries[line.vat_rate.id] = Number(line.vat_rate.rate)
    }
  }
  return entries
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

  const schema = useMemo(() => (isEdit ? buildUpdateQuoteSchema(t) : buildCreateQuoteSchema(t)), [isEdit, t])

  const defaultValues = useMemo<QuoteFormValues>(() => {
    if (mode.type === 'edit') {
      const { quote } = mode
      return {
        code: quote.code,
        title: quote.title,
        opportunity_id: quote.opportunity_id,
        quote_status_id: quote.quote_status_id,
        commercial_id: quote.commercial_id,
        reporter_id: quote.reporter_id,
        supervisor_id: quote.supervisor_id,
        company_id: quote.company_id,
        company_site_id: quote.company_site_id,
        operational_site_id: quote.operational_site_id,
        layout_id: quote.layout_id,
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
      quote_status_id: null,
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
      internal_notes: null,
      offer_lines: [],
      cost_lines: [],
    }
  }, [mode, initialCode])

  const form = useForm<QuoteFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

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

  return { form, isEdit, serverError, onSubmit, vatRatePercentFor, rememberVatRatePercent }
}
