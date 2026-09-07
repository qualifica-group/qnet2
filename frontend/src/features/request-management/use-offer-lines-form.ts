import { useCallback, useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { z } from 'zod'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { opportunityDetailQueryKey } from '@/features/opportunities/api'
import {
  linesToFormValues,
  originalLineInputs,
  sameLines,
  toLineInputs,
  vatRatePercentsFromLines,
} from '@/features/quotes/quote-line-values'
import { MAX_LINES_PER_TAB, quoteLineRowSchema } from '@/features/quotes/quote-schema'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import type { ProductLineRow } from '@/features/product-lines/types'
import { updateRequestWork } from '@/features/request-management/api'
import { requestManagementKeys } from '@/features/request-management/query-keys'
import { toProductLineRows } from '@/features/request-management/request-work-payload'
import type { RequestWorkPanelWithPermissions } from '@/features/request-management/types'

/**
 * The quick-edit form values: the offer rows, plus the classification they are
 * scoped by. `product_lines` is carried READ-ONLY — `RequestOfferLinesField`
 * watches it to scope the product picker and to resolve the `single`-mode row
 * cap — and never travels: this surface edits the offer rows alone (user
 * directive 2026-09-07), the classification keeps its own inline-editable
 * column on the same grid.
 */
export interface OfferLinesFormValues {
  product_lines: ProductLineRow[]
  offer_lines: QuoteLineFormValues[]
}

function buildDefaultValues(panel: RequestWorkPanelWithPermissions): OfferLinesFormValues {
  return {
    product_lines: toProductLineRows(panel.product_lines),
    // The SAME hydration the work panel uses, minus the provvigioni block the
    // endpoint prohibits on this channel.
    offer_lines: linesToFormValues(panel.offer_lines, false),
  }
}

interface UseOfferLinesFormOptions {
  /** Called after a successful save (the grid refreshes the row from it). */
  onSaved?: () => void
  /** Called once the dialog has nothing left to do — a save that succeeded, or a submit with no change. */
  onDone?: () => void
}

/**
 * RHF/Zod wiring of the grid's "Linee di prodotto" quick edit (user directive
 * 2026-09-07): the SAME per-row schema, the SAME hydration/wire mappers and
 * the SAME `PATCH /request-management/{quote}` the work panel goes through —
 * so a rule fixed on one surface cannot stay wrong on the other. The payload
 * is sparse in the same sense: an untouched collection never travels, and the
 * dialog closes without a request.
 */
export function useOfferLinesForm(
  panel: RequestWorkPanelWithPermissions,
  { onSaved, onDone }: UseOfferLinesFormOptions = {},
) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [submitError, setSubmitError] = useState<string | null>(null)

  const schema = useMemo(
    () =>
      z.object({
        // Watched only (see `OfferLinesFormValues`): no rule of its own here,
        // its collection-level rules belong to the surfaces that write it.
        product_lines: z.array(z.custom<ProductLineRow>()),
        offer_lines: z.array(quoteLineRowSchema(t)).max(MAX_LINES_PER_TAB, t('quotes.form.linesMax')),
      }),
    [t],
  )

  const defaultValues = useMemo(() => buildDefaultValues(panel), [panel])

  const form = useForm<OfferLinesFormValues>({ resolver: zodResolver(schema), defaultValues })

  // The VAT-percent cache the rows' live preview reads (AC-071), seeded from
  // the persisted lines' own hydrated rate: the `vat-rates/for-select` picker
  // never exposes a percentage.
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

  // A per-row 422 (`offer_lines.0.quantity`) has a matching control — the row
  // editor binds each field by index — while the cross-row ones (the
  // single-category cap, the coverage rule) land on the collection root.
  const errorFields: Path<OfferLinesFormValues>[] = ['offer_lines']

  const onSubmit = form.handleSubmit(
    async (values) => {
      setSubmitError(null)

      // Step 1: the sparse diff, on the WIRE shape the endpoint reads.
      const lines = toLineInputs(values.offer_lines)
      if (sameLines(lines, originalLineInputs(panel.offer_lines, false))) {
        onDone?.()
        return
      }

      // Step 2: the module's own PATCH — the single choke point that drags
      // coverage, aggregates, derived name and workflow re-resolution
      // (RequestOfferLineWriter).
      try {
        const updated = await updateRequestWork(panel.id, { offer_lines: lines })
        queryClient.setQueryData(requestManagementKeys.panel(panel.id), updated)
        // The panel's id is the Offerta's; the opportunity detail cache is
        // keyed on the underlying Opportunity.
        queryClient.invalidateQueries({ queryKey: opportunityDetailQueryKey(panel.opportunity_id) })
        toast.success(t('requestManagement.workPanel.saved'))
        onSaved?.()
        onDone?.()
      } catch (error) {
        if (!applyServerValidationErrors(error, form.setError, errorFields)) {
          setSubmitError(t('requestManagement.workPanel.genericError'))
        }
      }
    },
    () => {
      setSubmitError(t('requestManagement.offerLines.validationSummary'))
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
